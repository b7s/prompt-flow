<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TelegramActivate extends Command
{
    protected $signature = 'telegram:activate';

    protected $description = 'Activate and validate Telegram bot token';

    /**
     * @throws ConnectionException
     */
    public function handle(): int
    {
        $token = config('prompt-flow.channels.telegram.bot_token');

        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in your .env file.');

            return self::FAILURE;
        }

        $this->info('Validating Telegram bot token...');

        $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

        if (! $response->successful()) {
            $error = $response->json('description', 'Unknown error');
            $this->error("Failed to validate bot token: {$error}");

            return self::FAILURE;
        }

        $bot = $response->json('result');
        $username = $bot['username'] ?? 'unknown';
        $firstName = $bot['first_name'] ?? 'Unknown Bot';

        $this->info('Telegram bot activated successfully!');
        $this->newLine();
        $this->line("  Bot Name: <info>{$firstName}</info>");
        $this->line("  Username: <info>@{$username}</info>");

        $this->setupWebhook($token);

        return self::SUCCESS;
    }

    /**
     * @throws ConnectionException
     */
    private function setupWebhook(string $token): void
    {
        $externalUrl = config('prompt-flow.external_url');

        if (empty($externalUrl)) {
            $this->newLine();
            $this->warn('APP_EXTERNAL_URL is not set.');
            $this->line('Set APP_EXTERNAL_URL in your .env file to configure webhook.');
            $this->line('Example: APP_EXTERNAL_URL=https://your-domain.com');

            return;
        }

        $webhookPath = url()->route('telegram.webhook', [], false);
        $webhookUrl = str_ends_with($externalUrl, $webhookPath)
            ? $externalUrl
            : rtrim($externalUrl, '/').$webhookPath;

        $this->newLine();
        $this->info('Setting up webhook...');

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
        ]);

        if (! $response->successful()) {
            $error = $response->json('description', 'Unknown error');
            $this->error("Failed to set webhook: {$error}");

            return;
        }

        $this->info('Webhook configured successfully!');
        $this->line("  URL: <info>{$webhookUrl}</info>");
    }
}
