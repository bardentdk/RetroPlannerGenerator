<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class AttendanceAnalyzer
{
    public function analyzePage(string $imagePath): array
    {
        try {
            $image = file_get_contents($imagePath);
            $base64Image = base64_encode($image);
            $dataUri = "data:image/jpeg;base64,{$base64Image}";

            $prompt = <<<EOT
            ANALYSE CETTE PAGE DE PLANNING DE FORMATION.
            
            OBJECTIF : Extraire la DATE et le MODULE pour construire un CALENDRIER.
            
            RÈGLES :
            1. **DATE** : Cherche la date exacte (YYYY-MM-DD). Si introuvable, renvoie vide.
            2. **PÉRIODE** : 
               - Matin (env. 08:30-12:30) -> "morning"
               - Après-midi (env. 13:30-16:30) -> "afternoon"
            3. **NOM ÉTUDIANT** : Cherche le nom du stagiaire.
               - IMPORTANT : S'il n'y a PAS de nom clair, renvoie "PLANNING_GLOBAL".
            4. **MODULE** : Le titre du cours.
            5. **INTERVENANT** : Le nom du formateur.
            
            Renvoie UNIQUEMENT ce JSON :
            {
                "student_name": "String",
                "date": "YYYY-MM-DD",
                "period": "morning" | "afternoon",
                "module_name": "String",
                "instructor_name": "String"
            }
            EOT;

            $response = OpenAI::chat()->create([
                'model' => 'gpt-5-mini',
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
                // 'temperature' => 1,
                // AUCUN paramètre max_tokens ici pour éviter les bugs
            ]);

            $content = $response->choices[0]->message->content;
            
            // Nettoyage du JSON
            $cleaned = str_replace(['```json', '```', "\n"], '', $content);
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');
            
            if ($start !== false && $end !== false) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
                return json_decode(trim($cleaned), true) ?? [];
            }
            
            return [];

        } catch (\Exception $e) {
            Log::error("Erreur OpenAI Service : " . $e->getMessage());
            return [];
        }
    }
}