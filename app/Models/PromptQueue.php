<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptQueue extends Model
{
    protected $fillable = [
        'project_id',
        'project_path',
        'session_id',
        'prompt',
        'status',
        'position',
        'chat_id',
        'channel',
        'processed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
