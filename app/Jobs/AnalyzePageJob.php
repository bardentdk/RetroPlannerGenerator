<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
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
    public $tries = 3;

    protected $imagePath;
    protected $attendanceFileId;
    protected $filename;

    public function __construct($imagePath, $attendanceFileId, $filename)
    {
        $this->imagePath = $imagePath;
        $this->attendanceFileId = $attendanceFileId;
        $this->filename = $filename;
    }

    // --- ICI : PAS D'ARGUMENT DANS LES PARENTHÈSES ---
    public function handle() 
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        // On instancie MANUELLEMENT pour contourner le bug d'injection
        // Le backslash \ au début est important pour forcer le chemin absolu
        $analyzer = new \App\Services\AttendanceAnalyzer(); 

        try {
            if (file_exists($this->imagePath)) {
                $data = $analyzer->analyzePage($this->imagePath);

                // --- SÉCURITÉ CONTRE LES PAGES VIDES ---
                if (empty($data) || empty($data['date'])) {
                    Log::warning("Page ignorée (Pas de date) : " . $this->filename);
                    @unlink($this->imagePath);
                    return;
                }

                // --- SÉCURITÉ DATE ---
                $dateValide = false;
                try {
                    if (strtotime($data['date']) !== false) $dateValide = true;
                } catch (\Exception $e) {}

                if ($dateValide) {
                    $rawName = $data['student_name'] ?? 'PLANNING_GLOBAL';
                    $studentName = mb_strtoupper($this->forceUtf8($rawName));

                    $period = strtolower($data['period'] ?? 'morning');
                    if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                    TrainingSlot::updateOrCreate(
                        [
                            'student_name' => $studentName,
                            'date' => $data['date'],
                            'period' => $period,
                        ],
                        [
                            'module_name' => $this->forceUtf8($data['module_name'] ?? 'Formation'),
                            'instructor_name' => $this->forceUtf8($data['instructor_name'] ?? 'Non précisé'),
                            'source_file' => $this->filename,
                            'is_present' => true, 
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
            Log::error("Erreur Job : " . $e->getMessage());
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