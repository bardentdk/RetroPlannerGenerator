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
                $data = $analyzer->analyzePage($this->imagePath);

                // Si l'IA renvoie un tableau vide, on considère que c'est une erreur temporaire
                // et on lance une exception pour déclencher le "retry" automatique
                if (empty($data)) {
                    throw new \Exception("Réponse IA vide ou invalide (Rate Limit probable)");
                }

                if (!empty($data) && !empty($data['date'])) {
                    
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
                            'date' => $data['date'],
                            'period' => $period,
                        ],
                        [
                            'is_present' => $data['is_signed'] ?? false,
                            'module_name' => $moduleName,
                            'instructor_name' => $instructorName,
                            'source_file' => $this->filename,
                        ]
                    );
                }
                
                // Suppression de l'image seulement si succès
                @unlink($this->imagePath);
                
                $file = AttendanceFile::find($this->attendanceFileId);
                if ($file) {
                    $file->increment('processed_pages');
                    if ($file->processed_pages >= $file->total_pages) {
                        $file->update(['status' => 'completed']);
                    }
                }
            }
        } catch (\Exception $e) {
            // Si c'est une erreur de Rate Limit (429), Laravel va utiliser la fonction backoff()
            // On log juste un warning pour info
            Log::warning("Tentative échouée pour une page (Retry prévu) : " . $e->getMessage());
            
            // On RELANCE l'erreur pour que Laravel sache qu'il faut ré-essayer plus tard
            throw $e;
        }
    }

    private function forceUtf8($string)
    {
        if (is_null($string)) return '';
        if (mb_check_encoding($string, 'UTF-8')) return $string;
        return iconv('Windows-1252', 'UTF-8//IGNORE', $string) ?: $string;
    }
}