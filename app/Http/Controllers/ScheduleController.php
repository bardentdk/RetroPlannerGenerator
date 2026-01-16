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
    public function exportPdf(Request $request)
    {
        $targetStudent = $request->input('student');

        if (!$targetStudent) {
            return redirect()->back()->withErrors(['file' => 'Veuillez sélectionner un apprenant.']);
        }

        $days = TrainingSlot::where('student_name', $targetStudent)
            ->whereNotNull('date')
            ->orderBy('date')
            ->get()
            ->groupBy(function($item) {
                return $item->date->format('Y-m-d');
            });

        if ($days->isEmpty()) return redirect()->back()->withErrors(['file' => 'Aucune donnée.']);

        $trainingName = "TP Gestionnaire de Paie"; // Nom fixe ou dynamique
        $filename = 'Relevé_' . Str::slug($targetStudent) . '.pdf';

        // --- ICI LE CHANGEMENT ---
        // On charge la nouvelle vue "modern_schedule"
        $pdf = Pdf::loadView('pdf.modern_schedule', [
            'days' => $days, 
            'studentName' => $targetStudent,
            'trainingName' => $trainingName
        ])->setPaper('a4', 'portrait'); // Portrait est souvent plus élégant pour ce style
        
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