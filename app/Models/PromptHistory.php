<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptHistory extends Model
{
    protected $fillable = [
        'project_id',
        'user_prompt',
        'ai_response',
        'cli_type',
        'session_id',
        'is_continued',
        'continued_from_id',
    ];

    protected $casts = [
        'is_continued' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function continuedFrom(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'continued_from_id');
    }

    public function continuations(): HasMany
    {
        return $this->hasMany(__CLASS__, 'continued_from_id');
    }
}
