<?php

namespace App\Enums;

enum ChannelType: string
{
    case Telegram = 'telegram';
    case Web = 'web';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return __('messages.channel_type.'.$this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
