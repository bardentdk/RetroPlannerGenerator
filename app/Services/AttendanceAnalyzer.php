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
        
        YOUR GOAL: Extract the TRAINING SESSION details (Date, Period, Module).
        
        INSTRUCTIONS:
        1. **Date & Period**: Find the date (YYYY-MM-DD) and time (Morning/Afternoon). This is MANDATORY.
        2. **Module & Instructor**: Extract the course name and instructor name.
        3. **Student Name**: 
           - Look for a specific student name.
           - IF THE PAGE IS A BLANK TEMPLATE OR NO STUDENT IS FOUND: Return "PLANNING_REF".
           - Do NOT return "IGNORE". We want to keep the date even if no one is there.
        4. **Signature**: Check if signed.
        
        Return JSON only:
        {
            "student_name": "String" (or "PLANNING_REF"),
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
                'temperature' => 0.0,
                'max_tokens' => 400,
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