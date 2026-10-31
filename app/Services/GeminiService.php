<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Genera una respuesta de texto con Gemini.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Roles: "user" | "assistant".
     */
    public function reply(string $systemPrompt, array $history, string $message, float $temperature = 0.6): ?string
    {
        $contents = [];
        foreach ($history as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $turn['content']]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => 700],
        ];

        $keys = array_values(array_filter([
            config('services.gemini.api_key'),
            config('services.gemini.api_key_secondary'),
        ]));

        foreach ($keys as $key) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])
                    ->connectTimeout(5)
                    ->timeout(25)
                    ->post(self::BASE_URL.config('services.gemini.model').':generateContent', $payload);
            } catch (\Throwable $exception) {
                Log::warning('Gemini no disponible.', ['exception' => $exception::class]);

                continue;
            }

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            } else {
                Log::warning('Gemini respondió con error.', ['status' => $response->status()]);
            }
        }

        return null;
    }
}
