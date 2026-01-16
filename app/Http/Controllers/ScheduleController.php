<?php

namespace App\Http\Controllers;

use App\Models\TrainingSlot;
use App\Models\AttendanceFile;
use App\Jobs\ProcessAttendancePdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // 1. Récupérer le fichier en cours (pour la barre de progression globale)
        $currentFile = AttendanceFile::whereIn('status', ['pending', 'processing'])
                        ->latest()->first();

        // 2. Récupérer la liste UNIQUE des étudiants pour la sidebar (triée A-Z)
        // On utilise distinct() car un étudiant a plein de lignes
        $students = TrainingSlot::select('student_name')
                        ->distinct()
                        ->orderBy('student_name')
                        ->pluck('student_name');

        // 3. Gestion de la sélection d'un étudiant
        $selectedStudent = $request->input('student');
        $schedule = [];

        // Si aucun étudiant sélectionné, on prend le premier de la liste par défaut
        if (!$selectedStudent && $students->isNotEmpty()) {
            $selectedStudent = $students->first();
        }

        // 4. Charger le planning de l'étudiant sélectionné
        if ($selectedStudent) {
            $schedule = TrainingSlot::where('student_name', $selectedStudent)
                ->whereNotNull('date')
                ->orderBy('date')
                ->get()
                ->groupBy(fn($i) => $i->date->format('Y-m-d'));
        }
        
        return Inertia::render('Planning/Index', [
            'schedule' => $schedule,
            'students' => $students,         // La liste pour la sidebar
            'selectedStudent' => $selectedStudent, // Celui qu'on regarde
            'currentFile' => $currentFile 
        ]);
    }

    // --- PDF EXPORT (Modifié pour exporter SEULEMENT l'étudiant sélectionné) ---
    // public function exportPdf(Request $request)
    // {
    //     $targetStudent = $request->input('student');

    //     if (!$targetStudent) {
    //         return redirect()->back()->withErrors(['file' => 'Veuillez sélectionner un apprenant.']);
    //     }

    //     // 1. On récupère d'abord tous les fichiers sources liés à cet étudiant
    //     // (Au cas où il y aurait plusieurs PDFs)
    //     $sourceFiles = TrainingSlot::where('student_name', $targetStudent)
    //         ->pluck('source_file')
    //         ->unique();

    //     if ($sourceFiles->isEmpty()) {
    //         return redirect()->back()->withErrors(['file' => 'Aucune donnée pour cet apprenant.']);
    //     }

    //     // 2. CONSTITUTION DU MASTER PLANNING (Le programme théorique)
    //     // On prend TOUS les créneaux de ces fichiers, peu importe l'élève.
    //     // On groupe par Date et Période pour avoir 1 seule ligne par créneau théorique.
    //     $masterSlots = TrainingSlot::whereIn('source_file', $sourceFiles)
    //         ->select('date', 'period', 'module_name', 'instructor_name')
    //         ->distinct() // Évite les doublons si 15 élèves ont la même ligne
    //         ->orderBy('date')
    //         ->get();

    //     // 3. RÉCUPÉRATION DES PRÉSENCES DE L'ÉLÈVE
    //     $studentSlots = TrainingSlot::where('student_name', $targetStudent)
    //         ->get()
    //         // On crée une clé unique "YYYY-MM-DD_period" pour retrouver facilement
    //         ->keyBy(function($item) {
    //             return $item->date->format('Y-m-d') . '_' . $item->period;
    //         });

    //     // 4. FUSION (Merge)
    //     // On parcourt le Master Planning et on injecte les données de l'élève
    //     $finalSchedule = [];
    //     $totalHeuresPrevues = 0;

    //     foreach ($masterSlots as $slot) {
    //         $dateKey = $slot->date->format('Y-m-d');
    //         $lookupKey = $dateKey . '_' . $slot->period;

    //         // Est-ce que l'élève a une ligne pour ce créneau ?
    //         $studentEntry = $studentSlots->get($lookupKey);

    //         // On construit l'objet pour la vue
    //         if (!isset($finalSchedule[$dateKey])) {
    //             $finalSchedule[$dateKey] = ['morning' => null, 'afternoon' => null];
    //         }

    //         // On prépare les données du créneau
    //         $slotData = [
    //             'module_name' => $slot->module_name,
    //             'instructor_name' => $slot->instructor_name,
    //             // Si l'élève a une entrée, on prend son statut, sinon "null" (considéré comme absent/non noté)
    //             'is_present' => $studentEntry ? $studentEntry->is_present : false,
    //             'has_data' => $studentEntry ? true : false // Pour savoir si on a une info ou pas
    //         ];

    //         $finalSchedule[$dateKey][$slot->period] = (object) $slotData;
            
    //         // On cumule 3.5h pour chaque créneau théorique existant
    //         $totalHeuresPrevues += 3.5; 
    //     }

    //     $trainingName = "TP Gestionnaire de Paie"; // Ou dynamique selon le module le plus fréquent
    //     $filename = 'Planning_' . \Illuminate\Support\Str::slug($targetStudent) . '.pdf';

    //     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.modern_schedule', [
    //         'schedule' => $finalSchedule, // On passe notre nouveau tableau fusionné
    //         'studentName' => $targetStudent,
    //         'trainingName' => $trainingName,
    //         'totalHeures' => $totalHeuresPrevues // On passe le total théorique
    //     ])->setPaper('a4', 'portrait');
        
    //     return $pdf->download($filename);
    // }

    public function exportPdf(Request $request)
    {
        $targetStudent = $request->input('student');

        if (!$targetStudent) {
            return redirect()->back()->withErrors(['file' => 'Veuillez sélectionner un apprenant.']);
        }

        // 1. On récupère les fichiers sources
        $sourceFiles = TrainingSlot::where('student_name', $targetStudent)
            ->pluck('source_file')
            ->unique();

        if ($sourceFiles->isEmpty()) {
            return redirect()->back()->withErrors(['file' => 'Aucune donnée pour cet apprenant.']);
        }

        // 2. MASTER PLANNING (Tous les créneaux théoriques existants en BDD)
        $masterSlots = TrainingSlot::select('date', 'period', 'module_name', 'instructor_name')
            ->whereNotNull('date')
            ->orderBy('date')
            ->get()
            // On dédoublonne pour avoir 1 ligne unique par créneau horaire
            ->unique(function ($item) {
                return $item->date->format('Y-m-d') . $item->period;
            });

        // 3. FUSION & CALCUL DU TOTAL THÉORIQUE
        $finalSchedule = [];
        $totalHeuresPrevues = 0;

        foreach ($masterSlots as $slot) {
            $dateKey = $slot->date->format('Y-m-d');

            if (!isset($finalSchedule[$dateKey])) {
                $finalSchedule[$dateKey] = ['morning' => null, 'afternoon' => null];
            }

            // On prépare juste les infos du cours (plus besoin de is_present)
            $slotData = [
                'module_name' => $slot->module_name,
                'instructor_name' => $slot->instructor_name,
            ];

            $finalSchedule[$dateKey][$slot->period] = (object) $slotData;
            
            // --- LE CALCUL EST ICI ---
            // On ajoute 3.5h pour chaque créneau existant dans le planning,
            // peu importe si l'élève a signé ou non.
            $totalHeuresPrevues += 3.5; 
        }

        $trainingName = "Calendrier de Formation";
        $filename = 'Planning_' . \Illuminate\Support\Str::slug($targetStudent) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.modern_schedule', [
            'schedule' => $finalSchedule,
            'studentName' => $targetStudent,
            'trainingName' => $trainingName,
            'totalHeures' => $totalHeuresPrevues
        ])->setPaper('a4', 'portrait');
        
        return $pdf->download($filename);
    }
    
    // ... (upload, status, update, destroy, reset, forceUtf8 restent identiques) ...
    public function upload(Request $request) {
        $request->validate(['file' => 'required|mimes:pdf|max:50000']);
        $file = $request->file('file');
        $filename = $this->forceUtf8($file->getClientOriginalName());
        $path = $file->store('uploads');
        $attendanceFile = AttendanceFile::create(['filename' => $filename, 'path' => $path, 'status' => 'pending']);
        ProcessAttendancePdf::dispatch($attendanceFile);
        return redirect()->route('home')->with('success', "Upload réussi.");
    }

    public function status($id)
    {
        // On utilise findOrFail pour renvoyer une 404 propre si l'ID n'existe pas,
        // mais normalement le polling s'arrête avant.
        return \App\Models\AttendanceFile::find($id);
    }

    public function update(Request $request, $id) {
        $slot = TrainingSlot::findOrFail($id);
        $slot->update($request->validate([
            'student_name' => 'required|string|max:255',
            'is_present' => 'required|boolean',
            'date' => 'required|date',
        ]));
        return redirect()->back()->with('success', 'Mis à jour.');
    }

    public function destroy($id) { TrainingSlot::findOrFail($id)->delete(); return redirect()->back(); }

    public function reset() { TrainingSlot::truncate(); AttendanceFile::truncate(); return redirect()->route('home'); }

    private function forceUtf8($string) {
        if (is_null($string)) return '';
        if (mb_check_encoding($string, 'UTF-8')) return $string;
        $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $string);
        return $converted !== false ? $converted : iconv(mb_detect_encoding($string, mb_detect_order(), true), 'UTF-8//IGNORE', $string);
    }
}