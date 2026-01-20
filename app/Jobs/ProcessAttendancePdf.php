<?php

namespace App\Jobs;

use App\Models\AttendanceFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Bus;

class ProcessAttendancePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes
    protected $fileId;

    // On force le typage INT pour l'ID
    public function __construct(int $fileId)
    {
        $this->fileId = $fileId;
    }

    public function handle()
    {
        // On récupère l'objet proprement
        $attendanceFile = AttendanceFile::find($this->fileId);

        // Si $attendanceFile est null, c'est que l'ID n'existe pas en base
        if (!$attendanceFile) {
            Log::error("Fichier introuvable en base pour l'ID : " . $this->fileId);
            return;
        }

        // Maintenant on est sûr que c'est un OBJET, donc on peut faire ->update()
        $attendanceFile->update(['status' => 'processing']);

        try {
            $pdfPath = Storage::path($attendanceFile->path);
            $tempDir = storage_path('app/temp_split_' . $this->fileId);

            // 1. Création dossier
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            // 2. DÉTECTION OS (Windows vs Linux)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows (Laragon)
                $gsBinary = 'gswin64c'; 
            } else {
                // Linux (Forge)
                $gsBinary = 'gs';
            }

            // 3. Commande Ghostscript
            // Sur Windows, on utilise des guillemets pour les chemins
            $outputPattern = $tempDir . DIRECTORY_SEPARATOR . "page_%d.jpg";
            
            $cmd = [
                $gsBinary,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=jpeg',
                '-dTextAlphaBits=4',
                '-dGraphicsAlphaBits=4',
                '-r300',
                "-sOutputFile={$outputPattern}",
                $pdfPath
            ];

            Log::info("Découpage PDF avec binaire : $gsBinary");

            $result = Process::timeout(1200)->run($cmd);

            if ($result->failed()) {
                // Si ça échoue, on tente la commande 32 bits pour Windows au cas où
                if ($gsBinary === 'gswin64c') {
                     Log::warning("Echec gswin64c, tentative avec gswin32c...");
                     $cmd[0] = 'gswin32c';
                     $result = Process::timeout(1200)->run($cmd);
                }
                
                if ($result->failed()) {
                    throw new \Exception("Ghostscript Error:\n" . $result->errorOutput() . "\nOutput: " . $result->output());
                }
            }

            // 4. Dispatch des jobs
            $files = glob($tempDir . DIRECTORY_SEPARATOR . "page_*.jpg");
            
            if (empty($files)) {
                throw new \Exception("Le découpage a réussi mais aucune image n'a été créée. Vérifiez les droits d'écriture.");
            }

            $attendanceFile->update(['total_pages' => count($files)]);

            $batchJobs = [];
            foreach ($files as $filePath) {
                $batchJobs[] = new AnalyzePageJob($filePath, $this->fileId, basename($filePath));
            }

            Bus::batch($batchJobs)
                ->name('Analyse PDF #' . $this->fileId)
                ->onQueue('default')
                ->dispatch();

        } catch (\Exception $e) {
            Log::error("Erreur ProcessAttendancePdf : " . $e->getMessage());
            $attendanceFile->update(['status' => 'failed']);
            
            // On lance l'erreur pour la voir dans le terminal
            throw $e;
        }
    }
}