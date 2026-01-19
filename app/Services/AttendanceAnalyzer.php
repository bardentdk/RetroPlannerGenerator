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

        // --- PROMPT "ASPIRATEUR DE PLANNING" ---
        $prompt = <<<EOT
        You are analyzing a training schedule/attendance document.
        
        YOUR GOAL: Extract the TRAINING SESSION details.
        
        INSTRUCTIONS:
        1. **Date & Period**: Find the date (YYYY-MM-DD).
           - "Matin" (08:30-12:30) = period: "morning"
           - "Après-midi" (13:30-16:30) = period: "afternoon"
        2. **Student Name**: 
           - Extract the MAIN student name listed at the top or in the "Apprenant" box.
           - IMPORTANT: Normalize name as "LASTNAME Firstname" (e.g., "BOYER Lorenza"). 
           - Always put the family name first in UPPERCASE.
           - IF NO STUDENT FOUND: Return "PLANNING_REF".
        3. **Instructor & Module**: Extract them.
        
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
                'temperature' => 1,
                // 'max_tokens' => 400,
                'max_completion_tokens' => 400,
            ]);

            $content = $response->choices[0]->message->content;
            
            // Nettoyage JSON classique
            $cleaned = str_replace(['```json', '```', "\n"], '', $content);
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');
            if ($start !== false && $end !== false) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
            }

            return json_decode(trim($cleaned), true) ?? [];

        } catch (\Exception $e) {
            Log::error("Erreur OpenAI : " . $e->getMessage());
            return [];
        }
    }
}