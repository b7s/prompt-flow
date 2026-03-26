<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLinearWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LinearWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->verifySignature($request)) {
            Log::warning('Linear webhook signature verification failed', [
                'signature' => $request->header('Linear-Signature'),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $action = $payload['action'] ?? null;
        $data = $payload['data'] ?? [];

        if ($action !== 'create' && $action !== 'update') {
            return response()->json(['status' => 'ignored', 'reason' => 'unsupported_action']);
        }

        $issueId = $data['id'] ?? null;
        $issueTitle = $data['title'] ?? 'Untitled Issue';
        $issueDescription = $data['description'] ?? '';

        if (! $issueId) {
            return response()->json(['error' => 'Missing issue ID'], 400);
        }

        ProcessLinearWebhookJob::dispatch(
            issueId: $issueId,
            issueTitle: $issueTitle,
            issueDescription: $issueDescription,
        );

        return response()->json(['status' => 'accepted'], 202);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('Linear-Signature');
        $secret = config('prompt-flow.linear.webhook_secret');

        if (empty($secret) || empty($signature)) {
            Log::debug('Linear webhook secret or signature missing, skipping verification');

            return true;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
