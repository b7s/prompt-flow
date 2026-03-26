<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelegramWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'chat' => ['required', 'array'],
            'message_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => __('validation.required', ['attribute' => 'message']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chat_id' => $this->input('chat', ['id']),
        ]);
    }
}
