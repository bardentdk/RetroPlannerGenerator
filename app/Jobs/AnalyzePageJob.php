<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
use App\Services\AttendanceAnalyzer; // <--- Import indispensable !
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
    public $tries = 5; // On réessaie 5 fois en cas de pépin

    protected $imagePath;
    protected $attendanceFileId;
    protected $filename;

    public function __construct($imagePath, $attendanceFileId, $filename)
    {
        $this->imagePath = $imagePath;
        $this->attendanceFileId = $attendanceFileId;
        $this->filename = $filename;
    }

    public function backoff()
    {
        return [5, 15, 30]; // Temps d'attente progressif
    }

    public function handle(AttendanceAnalyzer $analyzer)
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        try {
            if (file_exists($this->imagePath)) {
                // 1. Analyse via le Service
                $data = $analyzer->analyzePage($this->imagePath);

                // 2. Vérification que l'IA a renvoyé quelque chose
                if (empty($data) || empty($data['date'])) {
                    Log::warning("Page ignorée (Pas de date ou vide) : " . $this->filename);
                    @unlink($this->imagePath);
                    return;
                }

                // 3. Validation de la date pour éviter le crash "Unknown"
                $dateValide = false;
                try {
                    if (strtotime($data['date']) !== false) {
                        $dateValide = true;
                    }
                } catch (\Exception $e) { $dateValide = false; }

                if ($dateValide) {
                    
                    // Nettoyage et enregistrement
                    $rawName = $data['student_name'] ?? 'PLANNING_GLOBAL';
                    $studentName = mb_strtoupper($this->forceUtf8($rawName));

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
                            'module_name' => $moduleName,
                            'instructor_name' => $instructorName,
                            'source_file' => $this->filename,
                            'is_present' => true, 
                        ]
                    );
                } else {
                    Log::info("Date invalide ignorée ({$data['date']}) sur fichier : " . $this->filename);
                }

                // Nettoyage image
                @unlink($this->imagePath);
                
                // Progression du fichier parent
                $file = AttendanceFile::find($this->attendanceFileId);
                if ($file) {
                    $file->increment('processed_pages');
                    if ($file->processed_pages >= $file->total_pages) {
                        $file->update(['status' => 'completed']);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Erreur Job : " . $e->getMessage());
            // Si c'est une erreur temporaire (réseau/IA), on laisse Laravel réessayer (throw)
            // Sinon on supprime l'image pour ne pas bloquer
            if (!str_contains($e->getMessage(), 'Rate limit')) {
                @unlink($this->imagePath);
            }
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