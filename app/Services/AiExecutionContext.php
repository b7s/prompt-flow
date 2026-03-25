<?php

namespace App\Services;

use App\Enums\ChannelType;

class AiExecutionContext
{
    private static ?ChannelType $channel = null;

    private static mixed $chatId = null;

    public static function set(ChannelType $channel, mixed $chatId): void
    {
        self::$channel = $channel;
        self::$chatId = $chatId;
    }

    public static function getChannel(): ?ChannelType
    {
        return self::$channel;
    }

    public static function getChatId(): mixed
    {
        return self::$chatId;
    }

    public static function clear(): void
    {
        self::$channel = null;
        self::$chatId = null;
    }
}
