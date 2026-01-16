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

    public function handle(): void
    {
        $this->attendanceFile->update(['status' => 'processing']);
        $fullPath = Storage::path($this->attendanceFile->path);
        
        // Ton chemin GS
        // On cherche dans le .env, sinon on utilise 'gs' (commande par défaut sur Linux)
        $gsBinary = env('GHOSTSCRIPT_PATH', 'gs');
        
        // $gsBinary = 'C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe'; 
        $pdfPathForGs = str_replace('\\', '/', $fullPath);
        
        $tempDir = storage_path("app/temp_split_" . $this->attendanceFile->id);
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

        try {
            // 1. EXTRACTION EN MASSE
            $outputPattern = str_replace('\\', '/', $tempDir) . "/page_%d.jpg";
            
            $cmd = [
                $gsBinary, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=jpeg',
                '--permit-file-read='.$pdfPathForGs,
                '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-r300',
                "-sOutputFile={$outputPattern}",
                $pdfPathForGs
            ];

            Process::run($cmd);

            // 2. Compter les fichiers
            $files = glob($tempDir . "/page_*.jpg");
            $total = count($files);
            
            if ($total === 0) throw new \Exception("Aucune page extraite.");
            
            $this->attendanceFile->update(['total_pages' => $total]);

            // 3. PRÉPARATION DU BATCH
            $jobs = [];
            // On capture l'ID et le nom ici pour les passer aux jobs enfants
            $fileId = $this->attendanceFile->id;
            $fileName = $this->attendanceFile->filename;

            foreach ($files as $file) {
                $jobs[] = new AnalyzePageJob($file, $fileId, $fileName);
            }

            // --- CORRECTION DE L'ERREUR ICI ---
            // On passe seulement l'ID ($fileId) au callback 'then', c'est plus robuste.
            Bus::batch($jobs)
                ->then(function (Batch $batch) use ($fileId) {
                    // Ce code s'exécute quand TOUS les jobs sont finis avec succès
                    $file = AttendanceFile::find($fileId);
                    if ($file) {
                        $file->update(['status' => 'completed']);
                    }
                    
                    // Nettoyage du dossier temp (Optionnel, à décommenter si tu veux)
                    // $dir = storage_path("app/temp_split_" . $fileId);
                    // array_map('unlink', glob("$dir/*.*"));
                    // @rmdir($dir);
                })
                ->catch(function (Batch $batch, \Throwable $e) use ($fileId) {
                    // En cas d'erreur critique dans le batch
                    Log::error("Erreur dans le batch pour le fichier $fileId : " . $e->getMessage());
                    $file = AttendanceFile::find($fileId);
                    if ($file) $file->update(['status' => 'failed']);
                })
                ->dispatch();

        } catch (\Exception $e) {
            Log::error("Erreur découpage PDF : " . $e->getMessage());
            $this->attendanceFile->update(['status' => 'failed']);
        }
    }
}