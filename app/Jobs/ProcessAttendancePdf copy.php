<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use App\Models\TrainingSlot;
use App\Services\AttendanceAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessAttendancePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // On définit un timeout théorique très long (2 heures) pour Laravel
    public $timeout = 7200; 
    
    // Si ça plante, on ne réessaie pas automatiquement (pour éviter les boucles infinies)
    public $tries = 1;

    protected $attendanceFile;

    public function __construct(AttendanceFile $attendanceFile)
    {
        $this->attendanceFile = $attendanceFile;
    }

    public function handle(AttendanceAnalyzer $analyzer): void
    {
        // --- CORRECTIF STABILITÉ ---
        // 1. Désactiver la limite de temps PHP (pour éviter le crash "Maximum execution time exceeded")
        set_time_limit(0); 
        // 2. Augmenter la mémoire (le traitement d'image consomme beaucoup)
        ini_set('memory_limit', '1024M');
        // ---------------------------

        $this->attendanceFile->update(['status' => 'processing']);
        $fullPath = Storage::path($this->attendanceFile->path);
        
        // Ton chemin GS
        $gsBinary = 'C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe'; 
        $pdfPathForGs = str_replace('\\', '/', $fullPath);

        try {
            // 1. Comptage
            if ($this->attendanceFile->total_pages === 0) {
                $countResult = Process::run([
                    $gsBinary, '-q', '-dNODISPLAY', 
                    '--permit-file-read='.$pdfPathForGs, 
                    '-c', "($pdfPathForGs) (r) file runpdfbegin pdfpagecount = quit"
                ]);
                
                $output = trim($countResult->output());
                $lines = explode("\n", $output);
                $pageCount = (int) end($lines);
                
                if ($pageCount === 0) throw new \Exception("Erreur lecture PDF (0 pages).");
                $this->attendanceFile->update(['total_pages' => $pageCount]);
            }

            $limit = $this->attendanceFile->total_pages;

            // 2. Traitement Page par Page
            for ($i = 1; $i <= $limit; $i++) {
                
                // Petite pause pour laisser respirer le CPU
                // usleep(100000); // 0.1 seconde

                $imagePath = storage_path("app/temp_{$this->attendanceFile->id}_page_{$i}.jpg");
                $imagePathGs = str_replace('\\', '/', $imagePath);

                // Conversion 300 DPI (Haute qualité = prend du temps)
                Process::run([
                    $gsBinary, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=jpeg',
                    '--permit-file-read='.$pdfPathForGs,
                    '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-r300',
                    "-dFirstPage={$i}", "-dLastPage={$i}",
                    "-sOutputFile={$imagePathGs}",
                    $pdfPathForGs
                ]);

                if (file_exists($imagePath)) {
                    try {
                        $data = $analyzer->analyzePage($imagePath);

                        if (!empty($data) && !empty($data['date']) && isset($data['student_name'])) {

                            $studentName = $this->forceUtf8($data['student_name']);
                            
                            // On nettoie aussi le Module et le Formateur
                            $moduleName = $this->forceUtf8($data['module_name'] ?? 'Module Inconnu');
                            $instructorName = $this->forceUtf8($data['instructor_name'] ?? 'Intervenant');

                            $period = strtolower($data['period'] ?? 'morning');
                            if (!in_array($period, ['morning', 'afternoon'])) $period = 'morning';

                            TrainingSlot::create([
                                'date' => $data['date'],
                                'period' => $period,
                                'is_present' => $data['is_signed'] ?? false,
                                'student_name' => $studentName,
                                'source_file' => $this->attendanceFile->filename,
                                // --- NOUVEAUX CHAMPS ---
                                'module_name' => $moduleName,
                                'instructor_name' => $instructorName,
                            ]);
                        }
                    } catch (\Exception $eIA) {
                        Log::error("Erreur IA Page $i : " . $eIA->getMessage());
                    }
                    @unlink($imagePath);
                } else {
                    Log::warning("Impossible de générer l'image pour la page $i");
                }
                
                // Mise à jour BDD pour la barre de progression
                $this->attendanceFile->increment('processed_pages');
            }

            $this->attendanceFile->update(['status' => 'completed']);

        } catch (\Exception $e) {
            Log::error("Erreur Job CRITIQUE : " . $e->getMessage());
            $this->attendanceFile->update(['status' => 'failed']);
            // Pas de "throw $e" ici pour éviter que le worker ne redémarre le job en boucle
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