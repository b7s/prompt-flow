<?php

namespace App\Enums;

enum LinearStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Canceled = 'canceled';

    public function label(): string
    {
        return trans('messages.linear.status.'.$this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
