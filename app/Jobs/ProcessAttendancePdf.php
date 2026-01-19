<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use Illuminate\Bus\Batch; // Important d'importer Batch
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessAttendancePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; 
    protected $attendanceFile;

    public function __construct(AttendanceFile $attendanceFile)
    {
        $this->attendanceFile = $attendanceFile;
    }

    public function handle(AttendanceAnalyzer $analyzer)
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        try {
            if (file_exists($this->imagePath)) {
                $data = $analyzer->analyzePage($this->imagePath);

                // Si données vides, on log et on passe (le fichier n'avait peut-être pas de date visible)
                if (empty($data) || empty($data['date'])) {
                    Log::warning("Page sans date détectée : " . $this->filename);
                    @unlink($this->imagePath);
                    return;
                }

                // VALIDATION DATE
                $dateValide = false;
                try {
                    // On vérifie que c'est une vraie date et pas "Unknown"
                    if (strtotime($data['date']) !== false) {
                        $dateValide = true;
                    }
                } catch (\Exception $e) { $dateValide = false; }

                if ($dateValide) {
                    
                    // GESTION DU NOM
                    $rawName = $data['student_name'] ?? 'PLANNING_GLOBAL';
                    $studentName = mb_strtoupper($this->forceUtf8($rawName)); // Tout en MAJ pour éviter doublons

                    // GESTION MODULE / FORMATEUR
                    $moduleName = $this->forceUtf8($data['module_name'] ?? 'Formation');
                    $instructorName = $this->forceUtf8($data['instructor_name'] ?? 'Non précisé');
                    
                    // GESTION PÉRIODE
                    $period = strtolower($data['period'] ?? 'morning');
                    if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                    // SAUVEGARDE EN BDD
                    TrainingSlot::updateOrCreate(
                        [
                            // Clé unique pour éviter doublons : Même élève, même date, même période
                            'student_name' => $studentName,
                            'date' => $data['date'],
                            'period' => $period,
                        ],
                        [
                            // On met à jour les infos descriptives
                            'module_name' => $moduleName,
                            'instructor_name' => $instructorName,
                            'source_file' => $this->filename,
                            'is_present' => true, // On met true par défaut car c'est du prévisionnel
                        ]
                    );
                }

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
            Log::error("Erreur Job Page : " . $e->getMessage());
            // On ne supprime l'image que si c'est fini, sinon on pourrait vouloir réessayer
            if (!str_contains($e->getMessage(), 'Rate limit')) {
                @unlink($this->imagePath);
            }
            throw $e; // Pour que Laravel retente le job si c'est une erreur temporaire
        }
    }
}