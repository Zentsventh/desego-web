<?php

namespace Tests\Feature;

use App\Services\RestaurantPortal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.gemini.api_key' => 'primary-key', 'services.gemini.api_key_secondary' => 'secondary-key']);
        $this->mock(RestaurantPortal::class, fn ($mock) => $mock->shouldReceive('catalog')->andReturn([
            'products' => [['name' => 'Ceviche Clasico', 'category' => 'Ceviches', 'price' => 38, 'can_order' => true, 'description' => 'Pescado fresco']],
            'branch' => null,
            'delivery_fee' => 5,
        ]));
    }

    public function test_chatbot_replies_using_menu_context(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Tenemos Ceviche Clasico por S/ 38.00']]]]],
        ])]);

        $this->postJson('/chatbot/mensaje', ['message' => 'Que ceviches tienen?'])
            ->assertOk()
            ->assertJson(['ok' => true, 'reply' => 'Tenemos Ceviche Clasico por S/ 38.00']);

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'primary-key')
            && str_contains($request['system_instruction']['parts'][0]['text'], 'Ceviche Clasico'));
    }

    public function test_chatbot_falls_back_to_secondary_key(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => 'quota'], 429)
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Hola desde la clave secundaria']]]]]]),
        ]);

        $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])
            ->assertOk()
            ->assertJson(['reply' => 'Hola desde la clave secundaria']);
    }

    public function test_chatbot_returns_friendly_error_when_gemini_fails(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $this->postJson('/chatbot/mensaje', ['message' => 'Hola'])
            ->assertStatus(503)
            ->assertJson(['ok' => false]);
    }

    public function test_chatbot_validates_message(): void
    {
        $this->postJson('/chatbot/mensaje', ['message' => ''])->assertUnprocessable();
    }
}
