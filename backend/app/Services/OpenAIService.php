<?php

namespace App\Services;

use OpenAI\Client;

class OpenAIService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = \OpenAI::client(config('services.openai.key'));
    }

    /**
     * Generate company profile, vision, mission, history from a brief description.
     */
    public function generateCompanyContent(array $data): array
    {
        $companyName    = $data['company_name'] ?? 'the company';
        $companyAddress = $data['company_address'] ?? '';
        $userVision     = $data['company_vision'] ?? '';
        $userMission    = $data['company_mission'] ?? '';
        $userHistory    = $data['company_history'] ?? '';

        $prompt = <<<PROMPT
You are writing the Chapter I of a university internship portfolio.

Company: {$companyName}
Address: {$companyAddress}

Write the following sections in formal, professional English suitable for a college internship report.
Return ONLY valid JSON with no markdown:

{
  "company_profile": "2-3 paragraph overview of the company",
  "company_vision": "Vision statement",
  "company_mission": "Mission statement",
  "company_history": "3-4 paragraph history of the company"
}

Existing user inputs (expand and improve these, do not leave blank):
Vision: {$userVision}
Mission: {$userMission}
History: {$userHistory}
PROMPT;

        return $this->callJson($prompt);
    }

    /**
     * Generate Chapter III assessment content.
     */
    public function generateChapterThree(array $data): array
    {
        $student = $data['student_name'] ?? 'the student';
        $company = $data['company_name'] ?? 'the company';
        $course  = $data['course'] ?? 'Information Technology';

        $existing = [
            'professional_responsibilities' => $data['professional_responsibilities'] ?? '',
            'things_learned'               => $data['things_learned'] ?? '',
            'experience_with_people'       => $data['experience_with_people'] ?? '',
            'industry_standards'           => $data['industry_standards'] ?? '',
            'recommendations'              => $data['recommendations'] ?? '',
            'advice'                       => $data['advice'] ?? '',
        ];

        $prompt = <<<PROMPT
You are writing Chapter III of a university internship portfolio.

Student: {$student}
Course: {$course}
Company: {$company}

Write formal, first-person reflective content for a {$course} student. Return ONLY valid JSON:

{
  "professional_responsibilities": "3 paragraphs on professional, ethical, and legal responsibilities as a future IT professional",
  "things_learned": "2 paragraphs on technical and soft skills gained",
  "experience_with_people": "2 paragraphs on interpersonal experiences",
  "industry_standards": "2 paragraphs on industry best practices observed",
  "recommendations": "2 paragraphs on how the internship program can be improved",
  "advice": "2 paragraphs of advice to future interns"
}

Existing student inputs (expand/improve):
Professional Responsibilities: {$existing['professional_responsibilities']}
Things Learned: {$existing['things_learned']}
Experience: {$existing['experience_with_people']}
Industry Standards: {$existing['industry_standards']}
Recommendations: {$existing['recommendations']}
Advice: {$existing['advice']}
PROMPT;

        return $this->callJson($prompt);
    }

    /**
     * Generate weekly report content from week summary.
     */
    public function generateWeeklyReport(int $weekNum, string $summary, string $student, string $company): string
    {
        if (trim($summary) === '') {
            $summary = 'general internship tasks and responsibilities';
        }

        $prompt = <<<PROMPT
Write a formal weekly internship journal entry for Week {$weekNum}.
Student: {$student}
Company: {$company}
Summary: {$summary}

Write 2-3 paragraphs in first person, formal language describing:
1. What was accomplished this week.
2. Challenges encountered.
3. New skills or knowledge gained.

Return ONLY the plain text paragraphs, no JSON, no headers.
PROMPT;

        $response = $this->client->chat()->create([
            'model'    => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return trim($response->choices[0]->message->content ?? '');
    }

    /**
     * Generate title, explanation, and reflection for a photo.
     */
    public function generatePhotoContent(string $context, string $student, string $company, int $weekNum = 0): array
    {
        $weekLabel = $weekNum > 0 ? "Week {$weekNum}" : 'an internship activity';

        $prompt = <<<PROMPT
An intern uploaded a photo from their internship. Generate professional captions.

Student: {$student}
Company: {$company}
Week: {$weekLabel}
Photo context/brief: {$context}

Return ONLY valid JSON:
{
  "title": "Short 4-8 word photo title",
  "explanation": "2-3 sentences describing what is happening in the photo",
  "reflection": "2-3 sentences on what this activity meant to the intern"
}
PROMPT;

        return $this->callJson($prompt, [
            'title'       => $context ?: 'Internship Activity',
            'explanation' => 'The photo was taken during an internship activity at ' . $company . '.',
            'reflection'  => 'This activity contributed to the intern\'s professional growth.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function callJson(string $prompt, array $fallback = []): array
    {
        try {
            $response = $this->client->chat()->create([
                'model'    => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            // Strip possible markdown code fences
            $content = preg_replace('/^```json\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/', '', $content);

            return json_decode($content, true) ?? $fallback;
        } catch (\Throwable $e) {
            \Log::error('OpenAI error: ' . $e->getMessage());
            return $fallback;
        }
    }
}
