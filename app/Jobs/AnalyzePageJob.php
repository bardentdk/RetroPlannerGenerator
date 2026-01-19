<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
use App\Services\AttendanceAnalyzer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzePageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    
    // --- NOUVEAU : On essaie jusqu'à 10 fois avant d'abandonner ---
    public $tries = 10; 

    protected $imagePath;
    protected $attendanceFileId;
    protected $filename;

    public function __construct($imagePath, $attendanceFileId, $filename)
    {
        $this->imagePath = $imagePath;
        $this->attendanceFileId = $attendanceFileId;
        $this->filename = $filename;
    }

    // --- NOUVEAU : Stratégie d'attente progressive (en secondes) ---
    // Si ça plante, on attend 5s, puis 15s, 30s, 60s, 120s...
    public function backoff()
    {
        return [5, 15, 30, 60, 120, 120, 120];
    }

    public function handle(AttendanceAnalyzer $analyzer)
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        try {
            if (file_exists($this->imagePath)) {
                // 1. Analyse par l'IA
                $data = $analyzer->analyzePage($this->imagePath);

                // 2. Vérification de sécurité : Si l'IA échoue ou renvoie vide
                if (empty($data)) {
                    // On ne lance pas d'exception pour ne pas bloquer la queue, on log juste
                    Log::warning("Page ignorée (Données vides) : " . $this->filename);
                    @unlink($this->imagePath);
                    return; 
                }

                // 3. Vérification de la DATE (C'est ici que ça plantait avant !)
                // On vérifie si la date est valide et n'est pas "Unknown"
                $dateString = $data['date'] ?? null;
                $isValidDate = $dateString && strtotime($dateString) !== false;

                if ($isValidDate) {
                    
                    $rawName = $data['student_name'] ?? 'PLANNING_REF';
                    if (strtoupper($rawName) === 'IGNORE') $rawName = 'PLANNING_REF'; 

                    $studentName = $this->forceUtf8($rawName);
                    $moduleName = $this->forceUtf8($data['module_name'] ?? 'Formation');
                    $instructorName = $this->forceUtf8($data['instructor_name'] ?? 'Non précisé');
                    
                    $period = strtolower($data['period'] ?? 'morning');
                    if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                    TrainingSlot::updateOrCreate(
                        [
                            'student_name' => $studentName,
                            'date' => $data['date'], // On utilise la date validée
                            'period' => $period,
                        ],
                        [
                            'is_present' => $data['is_signed'] ?? false,
                            'module_name' => $moduleName,
                            'instructor_name' => $instructorName,
                            'source_file' => $this->filename,
                        ]
                    );
                } else {
                    Log::info("Page ignorée (Date invalide ou inconnue) : " . $this->filename);
                }
                
                // Nettoyage
                @unlink($this->imagePath);
                
                // Mise à jour de la progression
                $file = AttendanceFile::find($this->attendanceFileId);
                if ($file) {
                    $file->increment('processed_pages');
                    if ($file->processed_pages >= $file->total_pages) {
                        $file->update(['status' => 'completed']);
                    }
                }
            }
        } catch (\Exception $e) {
            // En cas de Rate Limit, on relance (le backoff gérera l'attente)
            if (str_contains($e->getMessage(), 'Rate limit')) {
                throw $e;
            }
            // Pour les autres erreurs, on log mais on ne fait pas planter tout le process
            Log::error("Erreur page (Job supprimé) : " . $e->getMessage());
            @unlink($this->imagePath);
        }
    }

    private function forceUtf8($string)
    {
        if (is_null($string)) return '';
        if (mb_check_encoding($string, 'UTF-8')) return $string;
        return iconv('Windows-1252', 'UTF-8//IGNORE', $string) ?: $string;
    }
}