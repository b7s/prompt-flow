<?php

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('prompt-flow.providers.gemini', ['url' => 'https://generativelanguage.googleapis.com/v1']);
    Config::set('prompt-flow.providers.anthropic', ['url' => 'https://api.anthropic.com/v1/models']);
});

test('webhook endpoint accepts valid request with bearer token', function () {
    ApiKey::factory()->create([
        'name' => 'Test API Key',
        'key' => hash('sha256', 'sk_test_valid_key'),
        'is_active' => true,
    ]);

    $response = $this->withToken('sk_test_valid_key')->postJson('/api/webhook', [
        'message' => 'Test message from Telegram',
        'channel' => 'telegram',
        'chat_id' => 12345,
    ]);

    $response->assertStatus(202);
});

test('webhook endpoint rejects request without bearer token', function () {
    $response = $this->postJson('/api/webhook', [
        'message' => 'Test message',
        'channel' => 'telegram',
        'chat_id' => 12345,
    ]);

    $response->assertStatus(401);
});

test('webhook endpoint rejects request with invalid channel', function () {
    ApiKey::factory()->create([
        'key' => hash('sha256', 'sk_test_key'),
        'is_active' => true,
    ]);

    $response = $this->withToken('sk_test_key')->postJson('/api/webhook', [
        'message' => 'Test message',
        'channel' => 'invalid',
        'chat_id' => 12345,
    ]);

    $response->assertStatus(422);
});
