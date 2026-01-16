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
        if ($this->batch() && $this->batch()->cancelled()) return;

        try {
            if (file_exists($this->imagePath)) {
                $data = $analyzer->analyzePage($this->imagePath);

                // On accepte la donnée SI on a une date, même sans élève précis
                if (!empty($data) && !empty($data['date'])) {
                    
                    // Si l'IA n'a trouvé personne ou a mis le code générique
                    $rawName = $data['student_name'] ?? 'PLANNING_REF';
                    if (strtoupper($rawName) === 'IGNORE') $rawName = 'PLANNING_REF'; // Sécurité

                    $studentName = $this->forceUtf8($rawName);
                    $moduleName = $this->forceUtf8($data['module_name'] ?? 'Formation');
                    $instructorName = $this->forceUtf8($data['instructor_name'] ?? 'Non précisé');
                    
                    $period = strtolower($data['period'] ?? 'morning');
                    if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                    // On enregistre !
                    TrainingSlot::updateOrCreate(
                        [
                            'student_name' => $studentName, // Peut être "PLANNING_REF"
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
            Log::error("Erreur analyse page : " . $e->getMessage());
        }
    }

    private function forceUtf8($string)
    {
        if (is_null($string)) return '';
        if (mb_check_encoding($string, 'UTF-8')) return $string;
        return iconv('Windows-1252', 'UTF-8//IGNORE', $string) ?: $string;
    }
}