<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
// 👇👇 C'EST CETTE LIGNE QUI MANQUE ET QUI CRÉE L'ERREUR 👇👇
use App\Services\AttendanceAnalyzer; 
// 👆👆----------------------------------------------------👆👆
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
    public $tries = 5;

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
        return [5, 15, 30];
    }

    public function handle() 
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        // On l'instancie manuellement ici
        // Assure-toi d'avoir gardé le "use App\Services\AttendanceAnalyzer;" en haut
        $analyzer = new \App\Services\AttendanceAnalyzer(); 

        try {
            if (file_exists($this->imagePath)) {
                // On utilise notre objet créé manuellement
                $data = $analyzer->analyzePage($this->imagePath);

                // 2. Vérification Données
                if (empty($data) || empty($data['date'])) {
                    Log::warning("Page ignorée (Pas de date ou vide) : " . $this->filename);
                    @unlink($this->imagePath);
                    return;
                }

                // 3. Validation Date
                $dateValide = false;
                try {
                    if (strtotime($data['date']) !== false) {
                        $dateValide = true;
                    }
                } catch (\Exception $e) { $dateValide = false; }

                if ($dateValide) {
                    
                    // Nettoyage et Enregistrement
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
                    Log::info("Date invalide ignorée ({$data['date']}) sur : " . $this->filename);
                }

                // Nettoyage
                @unlink($this->imagePath);
                
                // Progression
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