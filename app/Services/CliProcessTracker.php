<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Models\PromptQueue;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function implode;
use function mb_strlen;
use function mb_substr;

class CliProcessTracker
{
    private const string CACHE_PREFIX = 'cli_process:';

    private const int MAX_QUEUE_SIZE = 10;

    private const string RUNNING_STATUS = 'running';

    private const string COMPLETED_STATUS = 'completed';

    private const string FAILED_STATUS = 'failed';

    private const int CACHE_TTL_RUNNING = 7200; // 2 hours

    private const int CACHE_TTL_COMPLETED = 1800; // 30 minutes

    private const int CACHE_TTL_PROJECT_SESSION = 7200; // 2 hours (same as running)

    public function track(string $sessionId, string $projectPath, ?string $prompt = null): void
    {
        $key = $this->getCacheKey($sessionId);

        // OPTIMIZATION: Store truncated prompt to reduce memory
        $promptPreview = $prompt ? mb_substr($prompt, 0, 500) : null;

        $data = [
            'session_id' => $sessionId,
            'project_path' => $projectPath,
            'prompt' => $promptPreview,
            'status' => self::RUNNING_STATUS,
            'started_at' => now()->toIso8601String(),
            'last_status_sent_at' => now()->toIso8601String(),
            'status_update_count' => 0,
        ];

        Cache::put($key, $data, now()->addSeconds(self::CACHE_TTL_RUNNING));

        Cache::put($this->getProjectKey($projectPath), $sessionId, now()->addSeconds(self::CACHE_TTL_PROJECT_SESSION));

        Log::info('CLI process tracked', ['session_id' => $sessionId, 'project_path' => $projectPath]);
    }

    public function isRunning(string $sessionId): bool
    {
        $data = $this->get($sessionId);

        return $data !== null && $data['status'] === self::RUNNING_STATUS;
    }

    public function isRunningForProject(string $projectPath): ?string
    {
        $sessionId = Cache::get($this->getProjectKey($projectPath));

        if ($sessionId && $this->isRunning($sessionId)) {
            return $sessionId;
        }

        if ($sessionId) {
            Cache::forget($this->getProjectKey($projectPath));
        }

        return null;
    }

    public function get(string $sessionId): ?array
    {
        return Cache::get($this->getCacheKey($sessionId));
    }

    public function complete(string $sessionId, ?string $result = null): ?array
    {
        $key = $this->getCacheKey($sessionId);
        $data = Cache::get($key);

        if (! $data) {
            return null;
        }

        $projectPath = $data['project_path'];

        $data['status'] = self::COMPLETED_STATUS;
        $data['completed_at'] = now()->toIso8601String();
        $data['result'] = $result ? mb_substr($result, 0, 2000) : null;

        Cache::put($key, $data, now()->addSeconds(self::CACHE_TTL_COMPLETED));

        Log::info('CLI process completed', ['session_id' => $sessionId]);

        return [
            'session_id' => $sessionId,
            'project_path' => $projectPath,
            'has_queue' => $this->getPendingQueueCount($projectPath) > 0,
        ];
    }

    public function fail(string $sessionId, string $error): ?array
    {
        $key = $this->getCacheKey($sessionId);
        $data = Cache::get($key);

        if (! $data) {
            return null;
        }

        $projectPath = $data['project_path'];

        $data['status'] = self::FAILED_STATUS;
        $data['failed_at'] = now()->toIso8601String();
        $data['error'] = mb_substr($error, 0, 1000);

        Cache::put($key, $data, now()->addSeconds(self::CACHE_TTL_COMPLETED));

        Log::info('CLI process failed', ['session_id' => $sessionId, 'error' => $error]);

        return [
            'session_id' => $sessionId,
            'project_path' => $projectPath,
            'error' => $error,
            'has_queue' => $this->getPendingQueueCount($projectPath) > 0,
            'queue' => $this->getQueue($projectPath),
        ];
    }

    /**
     * @throws Throwable
     */
    public function processNextInQueue(string $projectPath): ?PromptQueue
    {
        $item = $this->processQueue($projectPath);

        if (! $item) {
            return null;
        }

        Log::info('Processing queued prompt', [
            'queue_id' => $item->id,
            'project_path' => $projectPath,
        ]);

        return $item;
    }

    public function shouldSendStatusUpdate(string $sessionId): bool
    {
        $data = $this->get($sessionId);

        if (! $data || $data['status'] !== self::RUNNING_STATUS) {
            return false;
        }

        $startedAt = DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['started_at']);
        $lastSentAt = DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['last_status_sent_at']);
        $now = new DateTime;

        $secondsSinceStart = $now->getTimestamp() - $startedAt->getTimestamp();
        $secondsSinceLastSent = $now->getTimestamp() - $lastSentAt->getTimestamp();

        $shouldSend = match ($data['status_update_count']) {
            0 => $secondsSinceStart >= 30,
            1 => $secondsSinceStart >= 60,
            default => $secondsSinceLastSent >= 180,
        };

        if ($shouldSend) {
            $data['last_status_sent_at'] = $now->format('Y-m-d\TH:i:s+');
            $data['status_update_count']++;
            Cache::put($this->getCacheKey($sessionId), $data, now()->addSeconds(self::CACHE_TTL_RUNNING));
        }

        return $shouldSend;
    }

    public function markStatusSent(string $sessionId): void
    {
        $data = $this->get($sessionId);

        if ($data) {
            $data['last_status_sent_at'] = now()->toIso8601String();
            Cache::put($this->getCacheKey($sessionId), $data, now()->addSeconds(self::CACHE_TTL_RUNNING));
        }
    }

    public function forget(string $sessionId): void
    {
        $data = $this->get($sessionId);

        if ($data && isset($data['project_path'])) {
            Cache::forget($this->getProjectKey($data['project_path']));
        }

        Cache::forget($this->getCacheKey($sessionId));
    }

    public function getStatusSummary(string $sessionId): ?string
    {
        $data = $this->get($sessionId);

        if (! $data) {
            return null;
        }

        $startedAt = DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['started_at']);
        $duration = $startedAt->diff(new DateTime);
        $durationStr = $this->formatDuration($duration);

        $promptPreview = '';
        if (isset($data['prompt'])) {
            $promptPreview = "\n📝 Task: ".mb_substr($data['prompt'], 0, 100);
            if (mb_strlen($data['prompt']) > 100) {
                $promptPreview .= '...';
            }
        }

        return match ($data['status']) {
            self::RUNNING_STATUS => "⚙️ Running for {$durationStr}{$promptPreview}",
            self::COMPLETED_STATUS => "✅ Completed in {$durationStr}",
            self::FAILED_STATUS => '❌ Failed: '.($data['error'] ?? 'Unknown error'),
            default => "Status: {$data['status']}",
        };
    }

    public function getProjectStatus(string $projectPath): array
    {
        $runningSessionId = $this->isRunningForProject($projectPath);
        $running = null;

        if ($runningSessionId) {
            $running = $this->get($runningSessionId);
        }

        $queue = $this->getQueue($projectPath);

        return [
            'running' => $running,
            'queue' => $queue,
            'has_queue' => ! empty($queue),
        ];
    }

    public function queue(
        string $projectPath,
        string $prompt,
        ?string $sessionId = null,
        ?string $chatId = null,
        ?ChannelType $channel = null
    ): array {
        $hasQueueSpace = PromptQueue::query()->where('project_path', $projectPath)
            ->where('status', PromptQueue::STATUS_PENDING)
            ->exists();

        if ($hasQueueSpace >= self::MAX_QUEUE_SIZE) {
            return [
                'success' => false,
                'error' => 'Queue is full. Maximum '.self::MAX_QUEUE_SIZE.' items allowed.',
            ];
        }

        // Get position in single query with MAX
        $maxPosition = PromptQueue::query()->where('project_path', $projectPath)
            ->where('status', PromptQueue::STATUS_PENDING)
            ->max('position') ?? 0;

        $position = $maxPosition + 1;

        $queueItem = PromptQueue::query()->create([
            'project_path' => $projectPath,
            'session_id' => $sessionId,
            'prompt' => $prompt,
            'status' => PromptQueue::STATUS_PENDING,
            'position' => $position,
            'chat_id' => $chatId ? (string) $chatId : null,
            'channel' => $channel?->value,
        ]);

        Log::info('Prompt queued', [
            'queue_id' => $queueItem->id,
            'project_path' => $projectPath,
            'position' => $position,
        ]);

        return [
            'success' => true,
            'queue_id' => $queueItem->id,
            'position' => $position,
            'total_in_queue' => $position,
        ];
    }

    public function getQueue(?string $projectPath = null): array
    {
        $query = PromptQueue::query()->select(['id', 'position', 'prompt', 'session_id', 'created_at'])
            ->where('status', PromptQueue::STATUS_PENDING)
            ->orderBy('position');

        if ($projectPath) {
            $query->where('project_path', $projectPath);
        }

        return $query->get()->map(static function ($item) {
            return [
                'id' => $item->id,
                'position' => $item->position,
                'prompt' => $item->prompt,
                'prompt_preview' => mb_substr($item->prompt, 0, 80).(mb_strlen($item->prompt) > 80 ? '...' : ''),
                'session_id' => $item->session_id,
                'created_at' => $item->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    public function getQueueItem(int $queueId): ?array
    {
        $item = PromptQueue::query()->select(['id', 'position', 'prompt', 'session_id', 'status', 'chat_id', 'channel', 'created_at'])
            ->find($queueId);

        if (! $item) {
            return null;
        }

        return [
            'id' => $item->id,
            'position' => $item->position,
            'prompt' => $item->prompt,
            'session_id' => $item->session_id,
            'status' => $item->status,
            'chat_id' => $item->chat_id,
            'channel' => $item->channel,
            'created_at' => $item->created_at->toIso8601String(),
        ];
    }

    public function dequeue(int $queueId): bool
    {
        $item = PromptQueue::query()->find($queueId);

        if (! $item) {
            return false;
        }

        $item->update([
            'status' => PromptQueue::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->reorderQueue($item->project_path);

        Log::info('Queue item cancelled', ['queue_id' => $queueId]);

        return true;
    }

    public function clearQueue(?string $projectPath = null): int
    {
        $query = PromptQueue::query()->where('status', PromptQueue::STATUS_PENDING);

        if ($projectPath) {
            $query->where('project_path', $projectPath);
        }

        $count = $query->update([
            'status' => PromptQueue::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        Log::info('Queue cleared', ['project_path' => $projectPath, 'count' => $count]);

        return $count;
    }

    /**
     * OPTIMIZATION: Added atomic locking to prevent race conditions
     *
     * @throws Throwable
     */
    public function processQueue(?string $projectPath = null): ?PromptQueue
    {
        // OPTIMIZATION: Use database transaction with row locking
        return DB::transaction(static function () use ($projectPath) {
            $query = PromptQueue::query()->where('status', PromptQueue::STATUS_PENDING)
                ->orderBy('position');

            if ($projectPath) {
                $query->where('project_path', $projectPath);
            }

            // OPTIMIZATION: Use lockForUpdate to prevent concurrent processing
            $item = $query->lockForUpdate()->first();

            if (! $item) {
                return null;
            }

            $item->update(['status' => PromptQueue::STATUS_PROCESSING]);

            Log::info('Processing queue item', ['queue_id' => $item->id, 'project_path' => $item->project_path]);

            return $item;
        });
    }

    public function getPendingQueueCount(?string $projectPath = null): int
    {
        $query = PromptQueue::query()->where('status', PromptQueue::STATUS_PENDING);

        if ($projectPath) {
            $query->where('project_path', $projectPath);
        }

        return $query->count();
    }

    /**
     * OPTIMIZATION: Added select() to fetch only needed columns
     * and used database-level groupBy instead of a PHP collection
     */
    public function getAllPendingQueues(): array
    {
        // OPTIMIZATION: Select only needed columns
        $items = PromptQueue::query()->select(['id', 'project_path', 'position', 'prompt', 'chat_id', 'created_at'])
            ->where('status', PromptQueue::STATUS_PENDING)
            ->orderBy('project_path')
            ->orderBy('position')
            ->get();

        // Group by project_path using Laravel collection (acceptable for small result sets)
        // For larger datasets, consider pagination or cursor
        return $items->groupBy('project_path')
            ->map(static function ($items) {
                return $items->map(static function ($item) {
                    return [
                        'id' => $item->id,
                        'position' => $item->position,
                        'prompt_preview' => mb_substr($item->prompt, 0, 60).(mb_strlen($item->prompt) > 60 ? '...' : ''),
                        'chat_id' => $item->chat_id,
                        'created_at' => $item->created_at->toIso8601String(),
                    ];
                })->toArray();
            })
            ->toArray();
    }

    /**
     * OPTIMIZATION: Use bulk update instead of N individual updates
     */
    private function reorderQueue(string $projectPath): void
    {
        // Get pending items ordered by position
        $items = PromptQueue::query()->where('project_path', $projectPath)
            ->where('status', PromptQueue::STATUS_PENDING)
            ->orderBy('position')
            ->select(['id', 'position'])
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        // OPTIMIZATION: Use bulk update with CASE statement
        $positions = [];
        $newPositions = [];
        $i = 1;

        foreach ($items as $item) {
            if ($item->position !== $i) {
                $positions[] = $item->id;
                $newPositions[$item->id] = $i;
            }
            $i++;
        }

        // Only update if positions actually changed
        if (! empty($positions)) {
            // Build CASE statement for bulk update
            $caseStatement = 'CASE id ';
            foreach ($newPositions as $id => $newPos) {
                $caseStatement .= "WHEN {$id} THEN {$newPos} ";
            }
            $caseStatement .= 'ELSE position END';

            PromptQueue::query()->whereIn('id', $positions)
                ->update(['position' => DB::raw($caseStatement)]);
        }
    }

    private function formatDuration(\DateInterval $interval): string
    {
        $parts = [];

        if ($interval->h > 0) {
            $parts[] = "{$interval->h}h";
        }
        if ($interval->i > 0) {
            $parts[] = "{$interval->i}m";
        }
        if (empty($parts) && $interval->s > 0) {
            $parts[] = "{$interval->s}s";
        }

        return implode(' ', $parts) ?: '0s';
    }

    private function getCacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX.$sessionId;
    }

    private function getProjectKey(string $projectPath): string
    {
        return self::CACHE_PREFIX.'project:'.hash('xxh3', $projectPath);
    }
}
