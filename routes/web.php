<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScheduleController;

Route::get('/', [ScheduleController::class, 'index'])->name('home');
Route::post('/upload', [ScheduleController::class, 'upload'])->name('upload');
Route::post('/reset', [ScheduleController::class, 'reset'])->name('reset');

// --- NOUVELLES ROUTES CRUD ---
Route::put('/slot/{id}', [ScheduleController::class, 'update'])->name('slot.update');
Route::delete('/slot/{id}', [ScheduleController::class, 'destroy'])->name('slot.destroy');
Route::get('/export-pdf', [ScheduleController::class, 'exportPdf'])->name('export.pdf');
// --- C'EST CETTE LIGNE QUI MANQUE ---
Route::get('/upload/status/{id}', [ScheduleController::class, 'status'])->name('upload.status');
// Route temporaire pour lister les modèles Groq disponibles
// Route::get('/check-groq-models', function () {
//     $apiKey = env('OPENAI_API_KEY');
    
//     // On fait une requête CURL brute pour éviter les surcouches
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/models');
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         'Authorization: Bearer ' . $apiKey,
//         'Content-Type: application/json'
//     ]);
    
//     $response = curl_exec($ch);
//     curl_close($ch);
    
//     $data = json_decode($response, true);
    
//     // On affiche proprement
//     return response()->json($data);
// });