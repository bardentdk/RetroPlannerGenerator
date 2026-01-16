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

    public $timeout = 120; // 2 minutes max par page
    protected $imagePath;
    protected $attendanceFileId;
    protected $filename;

    public function __construct($imagePath, $attendanceFileId, $filename)
    {
        $this->imagePath = $imagePath;
        $this->attendanceFileId = $attendanceFileId;
        $this->filename = $filename;
    }

    public function handle(AttendanceAnalyzer $analyzer)
    {
        // Si le batch a été annulé, on arrête
        if ($this->batch() && $this->batch()->cancelled()) return;

        try {
            if (file_exists($this->imagePath)) {
                $data = $analyzer->analyzePage($this->imagePath);

                if (!empty($data) && !empty($data['date']) && isset($data['student_name'])) {
                    
                    // Logique identique à avant (Filtres, Save...)
                    if (strtoupper($data['student_name']) !== 'IGNORE' && 
                        stripos($data['student_name'], 'Formateur') === false) {

                        $studentName = $this->forceUtf8($data['student_name']);
                        $moduleName = $this->forceUtf8($data['module_name'] ?? 'Non spécifié');
                        $instructorName = $this->forceUtf8($data['instructor_name'] ?? 'Non spécifié');
                        
                        $period = strtolower($data['period'] ?? 'morning');
                        if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                        // --- CORRECTION ANTI-DOUBLONS ---
                        // On utilise updateOrCreate pour garantir 1 seule ligne par créneau
                        TrainingSlot::updateOrCreate(
                            [
                                // 1. Les critères d'unicité (La "Clé")
                                'student_name' => $studentName,
                                'date' => $data['date'],
                                'period' => $period,
                            ],
                            [
                                // 2. Les valeurs à mettre à jour ou insérer
                                'is_present' => $data['is_signed'] ?? false,
                                'module_name' => $moduleName,
                                'instructor_name' => $instructorName,
                                'source_file' => $this->filename,
                            ]
                        );
                    }
                }
                // Suppression de l'image temporaire après analyse
                @unlink($this->imagePath);
                
                // Mise à jour du compteur global
                $file = AttendanceFile::find($this->attendanceFileId);
                if ($file) {
                    $file->increment('processed_pages');
                    
                    // Si on a tout fini, on passe en completed
                    if ($file->processed_pages >= $file->total_pages) {
                        $file->update(['status' => 'completed']);
                        // Suppression du dossier temporaire ici si vous voulez
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Erreur analyse page unique : " . $e->getMessage());
            // On ne fait pas échouer tout le batch pour une page
        }
    }

    private function forceUtf8($string)
    {
        if (is_null($string)) return '';
        if (mb_check_encoding($string, 'UTF-8')) return $string;
        $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $string);
        return $converted !== false ? $converted : iconv(mb_detect_encoding($string, mb_detect_order(), true), 'UTF-8//IGNORE', $string);
    }
}