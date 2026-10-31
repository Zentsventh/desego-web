<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Services\RestaurantPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatbotController extends Controller
{
    public function message(Request $request, GeminiService $gemini, RestaurantPortal $portal): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:600',
            'history' => 'nullable|array|max:12',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1200',
        ]);

        $reply = $gemini->reply(
            $this->systemPrompt($portal),
            $validated['history'] ?? [],
            $validated['message'],
        );

        if ($reply === null) {
            return response()->json([
                'ok' => false,
                'reply' => 'Ahora mismo no puedo responder. Intenta de nuevo en unos minutos o escríbenos por reservas.',
            ], 503);
        }

        return response()->json(['ok' => true, 'reply' => $reply]);
    }

    private function systemPrompt(RestaurantPortal $portal): string
    {
        $context = Cache::remember('chatbot.store_context', 300, function () use ($portal): string {
            try {
                $catalog = $portal->catalog();
            } catch (\Throwable) {
                return 'No hay datos de la carta disponibles por ahora.';
            }

            $lines = collect($catalog['products'] ?? [])->map(fn (array $product): string => sprintf(
                '- %s (%s): S/ %s%s. %s',
                $product['name'] ?? '',
                $product['category'] ?? 'Carta',
                number_format((float) ($product['price'] ?? 0), 2),
                ($product['can_order'] ?? false) ? '' : ' [agotado]',
                $product['description'] ?? '',
            ))->implode("\n");

            $branch = $catalog['branch'] ?? null;
            $branchText = $branch
                ? sprintf(
                    'Local: %s, %s. Horario: %s a %s. Teléfono: %s.',
                    $branch->address ?? '',
                    $branch->district ?? '',
                    substr((string) ($branch->opening_time ?? ''), 0, 5),
                    substr((string) ($branch->closing_time ?? ''), 0, 5),
                    $branch->phone ?? '',
                )
                : '';

            return "CARTA ACTUAL:\n{$lines}\n\n{$branchText}\nCosto de delivery: S/ ".number_format((float) ($catalog['delivery_fee'] ?? 5), 2).'.';
        });

        return 'Eres el asistente virtual de DESEGO, una cevichería peruana. Responde siempre en español, de forma cálida, breve y útil (máximo 4 frases). '
            .'Ayuda con la carta, precios, ingredientes, alérgenos, delivery, recojo y reservas. Usa SOLO la información de abajo; si no sabes algo, dilo y sugiere llamar al local. '
            ."No inventes platos ni precios. Para pedir, indica que agreguen los platos al carrito; para reservar, que usen la sección Reservas.\n\n{$context}";
    }
}
