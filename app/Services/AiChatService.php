<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, source: string, model: string, used_tools: bool}|null
     */
    public function reply(string $prompt, array $history, string $message, array $tools, Closure $callTool): ?array
    {
        foreach (['gemini', 'groq'] as $provider) {
            $model = (string) config("services.{$provider}.model");
            $keys = array_unique(array_filter([
                config("services.{$provider}.api_key"),
                config("services.{$provider}.api_key_secondary"),
            ]));
            foreach ($keys as $slot => $key) {
                $cooldown = 'chatbot.ai.cooldown.'.hash('sha256', $provider.$model.$key);
                if (Cache::has($cooldown)) {
                    continue;
                }
                try {
                    $client = Http::acceptJson()->connectTimeout(3)->timeout(10);
                    if ($provider === 'gemini') {
                        $contents = [];
                        $lastRole = null;
                        foreach ($history as $turn) {
                            $role = $turn['role'] === 'assistant' ? 'model' : 'user';
                            if ($role === $lastRole && ! empty($contents)) {
                                $contents[count($contents) - 1]['parts'][0]['text'] .= "\n".$turn['content'];
                            } else {
                                $contents[] = ['role' => $role, 'parts' => [['text' => $turn['content']]]];
                                $lastRole = $role;
                            }
                        }
                        if ($lastRole === 'user' && ! empty($contents)) {
                            $contents[count($contents) - 1]['parts'][0]['text'] .= "\n".$message;
                        } else {
                            $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
                        }
                        $request = $client->withHeaders(['x-goog-api-key' => $key]);
                        $conversationContents = $contents;
                        $remainingToolCalls = 3;
                        $didCallTool = false;
                        $toolFailed = false;
                        for ($round = 0; $round < 4; $round++) {
                            $response = $request->post(
                                'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent',
                                [
                                    'system_instruction' => ['parts' => [['text' => $prompt]]],
                                    'contents' => $conversationContents,
                                    'tools' => [['functionDeclarations' => $tools]],
                                    'toolConfig' => ['functionCallingConfig' => ['mode' => 'AUTO']],
                                    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1000],
                                ],
                            );
                            if (! $response->successful()) {
                                break;
                            }
                            $candidate = $response->json('candidates.0.content');
                            if (! is_array($candidate) || ! is_array($candidate['parts'] ?? null)) {
                                break;
                            }
                            $functionCalls = array_values(array_filter(
                                $candidate['parts'],
                                fn (mixed $part): bool => is_array($part) && is_array($part['functionCall'] ?? null),
                            ));
                            if ($functionCalls === []) {
                                $text = $this->textFromParts($candidate['parts']);
                                if ($text !== '') {
                                    return ['reply' => $text, 'source' => $provider, 'model' => $model, 'used_tools' => $didCallTool && ! $toolFailed];
                                }
                                break;
                            }

                            $conversationContents[] = ['role' => 'model', 'parts' => $candidate['parts']];
                            $toolResponses = [];
                            foreach ($functionCalls as $functionCall) {
                                $call = $functionCall['functionCall'];
                                $name = $call['name'] ?? '';
                                $arguments = $call['args'] ?? [];
                                $isAllowedTool = is_string($name) && in_array($name, array_column($tools, 'name'), true);
                                if ($remainingToolCalls > 0 && $isAllowedTool && is_array($arguments)) {
                                    $result = $callTool($name, $arguments);
                                    $didCallTool = true;
                                    $toolFailed = $toolFailed || array_key_exists('error', $result);
                                } else {
                                    $result = ['error' => 'No fue posible realizar esa consulta.'];
                                    $toolFailed = true;
                                }
                                $remainingToolCalls--;
                                $toolResponses[] = ['functionResponse' => ['name' => is_string($name) ? $name : '', 'response' => ['result' => $result]]];
                            }
                            $conversationContents[] = ['role' => 'user', 'parts' => $toolResponses];
                        }
                    } else {
                        $request = $client->withToken($key);
                        $messages = [['role' => 'system', 'content' => $prompt], ...$history, ['role' => 'user', 'content' => $message]];
                        $remainingToolCalls = 3;
                        $didCallTool = false;
                        $toolFailed = false;
                        for ($round = 0; $round < 4; $round++) {
                            $response = $request->post('https://api.groq.com/openai/v1/chat/completions', [
                                'model' => $model,
                                'messages' => $messages,
                                'tools' => array_map(fn (array $tool): array => ['type' => 'function', 'function' => $this->openAiTool($tool)], $tools),
                                'tool_choice' => 'auto',
                                'temperature' => 0.4,
                                'max_completion_tokens' => 1200,
                            ]);
                            if (! $response->successful()) {
                                break;
                            }
                            $assistant = $response->json('choices.0.message');
                            if (! is_array($assistant)) {
                                break;
                            }
                            $toolCalls = $assistant['tool_calls'] ?? [];
                            if (! is_array($toolCalls) || $toolCalls === []) {
                                $text = $assistant['content'] ?? null;
                                if (is_string($text) && trim($text) !== '') {
                                    return ['reply' => trim($text), 'source' => $provider, 'model' => $model, 'used_tools' => $didCallTool && ! $toolFailed];
                                }
                                break;
                            }
                            $wellFormedToolCalls = collect($toolCalls)->every(fn (mixed $toolCall): bool => is_array($toolCall)
                                && is_string($toolCall['id'] ?? null)
                                && is_array($toolCall['function'] ?? null)
                                && is_string($toolCall['function']['name'] ?? null));
                            if (! $wellFormedToolCalls) {
                                break;
                            }

                            $messages[] = ['role' => 'assistant', 'content' => $assistant['content'] ?? null, 'tool_calls' => $toolCalls];
                            foreach ($toolCalls as $toolCall) {
                                $function = $toolCall['function'] ?? [];
                                $name = $function['name'] ?? '';
                                $arguments = json_decode((string) ($function['arguments'] ?? '{}'), true);
                                $isAllowedTool = is_string($name) && in_array($name, array_column($tools, 'name'), true);
                                if ($remainingToolCalls > 0 && $isAllowedTool && is_array($arguments)) {
                                    $result = $callTool($name, $arguments);
                                    $didCallTool = true;
                                    $toolFailed = $toolFailed || array_key_exists('error', $result);
                                } else {
                                    $result = ['error' => 'No fue posible realizar esa consulta.'];
                                    $toolFailed = true;
                                }
                                $remainingToolCalls--;
                                $messages[] = [
                                    'role' => 'tool',
                                    'tool_call_id' => $toolCall['id'] ?? '',
                                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                                ];
                            }
                        }
                    }
                    $retryAfter = $response->header('Retry-After');
                    $seconds = $response->status() === 429 ? 60 : 15;
                    if (is_string($retryAfter) && is_numeric($retryAfter)) {
                        $seconds = max(15, min(900, (int) $retryAfter));
                    }
                    if (in_array($response->status(), [401, 403], true)) {
                        $seconds = 300;
                    }
                    Cache::put($cooldown, true, $seconds);
                    Log::warning('Proveedor de chat no disponible.', ['provider' => $provider, 'slot' => $slot + 1, 'status' => $response->status()]);
                } catch (ConnectionException) {
                    Cache::put($cooldown, true, 15);
                    Log::warning('Tiempo de conexión del chat agotado.', ['provider' => $provider, 'slot' => $slot + 1]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function textFromParts(array $parts): string
    {
        return trim(collect($parts)
            ->filter(fn (mixed $part): bool => is_array($part) && is_string($part['text'] ?? null))
            ->pluck('text')->implode("\n"));
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function openAiTool(array $tool): array
    {
        $parameters = $tool['parameters'];
        $parameters['type'] = strtolower($parameters['type']);
        foreach ($parameters['properties'] as &$property) {
            $property['type'] = strtolower($property['type']);
        }
        unset($property);
        $tool['parameters'] = $parameters;

        return $tool;
    }
}
