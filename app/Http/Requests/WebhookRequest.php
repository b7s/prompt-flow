<?php

namespace App\Http\Requests;

use App\Enums\ChannelType;
use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'channel' => ['sometimes', 'in:'.implode(',', ChannelType::values())],
            'chat_id' => ['required'],
        ];
    }
}
