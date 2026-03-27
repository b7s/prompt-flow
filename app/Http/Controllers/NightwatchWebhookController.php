<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNightwatchWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NightwatchWebhookController
{
    /**
     * @throws \JsonException
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! config()->boolean('prompt-flow.nightwatch.enabled', false)) {
            return response()->json([
                'status' => 'ignored',
                'message' => '[NIGHTWATCH] '.__('messages.webhook.disabled'),
            ], 400);
        }

        if (! $this->verifySignature($request)) {
            Log::warning('Nightwatch webhook signature verification failed', [
                'signature' => $request->header('Nightwatch-Signature'),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        $event = $payload['event'] ?? null;
        $issueData = $payload['payload']['issue'] ?? [];

        if (! $event || ! $issueData) {
            return response()->json(['error' => 'Missing event or issue data'], 400);
        }

        $eventType = match ($event) {
            'issue.opened' => 'opened',
            'issue.resolved' => 'resolved',
            'issue.reopened' => 'reopened',
            'issue.ignored' => 'ignored',
            default => null,
        };

        if ($eventType === null) {
            Log::debug('Nightwatch webhook: unknown event type', ['event' => $event]);

            return response()->json(['status' => 'ignored', 'reason' => 'unknown_event']);
        }

        ProcessNightwatchWebhookJob::dispatch(
            eventType: $eventType,
            issueId: $issueData['id'] ?? null,
            issueRef: $issueData['ref'] ?? null,
            issueTitle: $issueData['title'] ?? 'Unknown Issue',
            issueType: $issueData['type'] ?? 'unknown',
            issueUrl: $issueData['url'] ?? null,
            issueDetails: $issueData['details'] ?? [],
        );

        return response()->json(['status' => 'accepted'], 202);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('Nightwatch-Signature');
        $secret = config('prompt-flow.nightwatch.webhook_secret');

        if (empty($secret) || empty($signature)) {
            Log::debug('Nightwatch webhook secret or signature missing, skipping verification');

            return true;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
