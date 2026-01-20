<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
// On garde l'import pour la forme, mais on va l'instancier manuellement
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

    // PAS d'argument dans la parenthèse pour éviter le bug "Target class does not exist"
    public function handle() 
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        // --- FIX BLOCAGE 0% : Instanciation manuelle ---
        $analyzer = new \App\Services\AttendanceAnalyzer(); 

        try {
            if (file_exists($this->imagePath)) {
                $data = $analyzer->analyzePage($this->imagePath);

                // Sécurité données vides
                if (empty($data) || empty($data['date'])) {
                    Log::warning("Page ignorée (Vide/Sans date) : " . $this->filename);
                    @unlink($this->imagePath);
                    return;
                }

                // Validation Date
                $dateValide = false;
                try {
                    if (strtotime($data['date']) !== false) $dateValide = true;
                } catch (\Exception $e) {}

                if ($dateValide) {
                    $rawName = $data['student_name'] ?? 'PLANNING_REF';
                    
                    // --- FIX DOUBLONS (Capture 1) ---
                    // On met tout en majuscules et on nettoie les espaces
                    $cleanName = mb_strtoupper(trim($this->forceUtf8($rawName)));
                    
                    // Si l'IA a mis "IGNORE" ou vide, on normalise
                    if ($cleanName === 'IGNORE' || $cleanName === '') {
                        $cleanName = 'PLANNING_REF';
                    }

                    $period = strtolower($data['period'] ?? 'morning');
                    if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                    // Sauvegarde
                    TrainingSlot::updateOrCreate(
                        [
                            'student_name' => $cleanName,
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
                
                // Mise à jour de la barre de progression
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