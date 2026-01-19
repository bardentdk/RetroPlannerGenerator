<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class AttendanceAnalyzer
{
    public function analyzePage(string $imagePath): array
    {
        $image = file_get_contents($imagePath);
        $base64Image = base64_encode($image);
        $dataUri = "data:image/jpeg;base64,{$base64Image}";

        // --- NOUVEAU PROMPT "PLANNING FIRST" ---
        $prompt = <<<EOT
        ANALYSE CETTE PAGE DE PLANNING DE FORMATION.
        
        OBJECTIF : Extraire la DATE et le MODULE prévu.
        Peu importe si la feuille est signée ou non. Peu importe si un nom d'étudiant est présent ou non.
        
        RÈGLES STRICTES :
        1. **DATE (OBLIGATOIRE)** : Trouve la date de la session (Format YYYY-MM-DD).
        2. **PÉRIODE (OBLIGATOIRE)** : 
           - Si horaires ~08:30-12:30 ou mention "Matin" -> "morning"
           - Si horaires ~13:30-16:30 ou mention "Après-midi" -> "afternoon"
        3. **MODULE** : Le nom du cours/module.
        4. **INTERVENANT** : Le nom du formateur.
        5. **ÉTUDIANT** : Le nom du stagiaire en haut de la page. 
           - SI AUCUN NOM N'EST CLAIREMENT INDIQUÉ : Renvoie "PLANNING_GLOBAL".
        
        Renvoie UNIQUEMENT ce JSON (sans markdown) :
        {
            "student_name": "Nom ou PLANNING_GLOBAL",
            "date": "YYYY-MM-DD",
            "period": "morning" ou "afternoon",
            "module_name": "Titre du module",
            "instructor_name": "Nom formateur"
        }
        EOT;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'chatgpt-4o-latest', // Le plus robuste
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => [
                                'url' => $dataUri,
                                'detail' => 'high'
                            ]],
                        ],
                    ],
                ],
                'temperature' => 0.0,
                // PAS DE PARAMÈTRE MAX_TOKENS ICI pour éviter l'erreur
            ]);

            $content = $response->choices[0]->message->content;
            
            // Nettoyage JSON basique
            $cleaned = str_replace(['```json', '```', "\n"], '', $content);
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');
            
            if ($start !== false && $end !== false) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
                return json_decode(trim($cleaned), true) ?? [];
            }
            
            // Si pas de JSON trouvé, on renvoie vide
            return [];

        } catch (\Exception $e) {
            Log::error("Erreur OpenAI : " . $e->getMessage());
            return [];
        }
    }
}