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

        // --- PROMPT "PLANNING COMPLET" ---
        $prompt = <<<EOT
        You are an AI that digitizes training schedules from attendance sheets.
        
        EXTRACT THE FOLLOWING DATA:
        1. **Module Name**: Look for "Module", "Intitulé", "Matière" or big titles like "GESTION PAIE", "DROIT SOCIAL". (Example: "Module 3 - Gestion Paie").
        2. **Instructor/Trainer Name**: Look for "Formateur", "Intervenant", "Dispensé par".
        3. **Student Name**: Look for "Stagiaire" or the specific student list.
        4. **Date**: YYYY-MM-DD.
        5. **Period**: Morning (Matin) or Afternoon (Après-midi).
        6. **Presence**: Is the STUDENT signed? (True/False).

        CRITICAL: 
        - If the extracted Instructor Name is empty, use "Intervenant non précisé".
        - If Module Name is empty, try to infer it from the context or use "Formation".

        Return JSON only:
        {
            "student_name": "String",
            "instructor_name": "String",
            "module_name": "String",
            "date": "YYYY-MM-DD",
            "period": "morning" | "afternoon", 
            "is_signed": boolean
        }
        EOT;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o', 
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
                'max_tokens' => 400,
            ]);

            $content = $response->choices[0]->message->content;
            
            // Nettoyage JSON
            $cleaned = str_replace(['```json', '```', "\n"], '', $content);
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');
            if ($start !== false && $end !== false) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
            }

            $json = json_decode(trim($cleaned), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Erreur JSON IA : " . $content);
                return [];
            }

            return $json ?? [];

        } catch (\Exception $e) {
            Log::error("Erreur OpenAI : " . $e->getMessage());
            return [];
        }
    }
}