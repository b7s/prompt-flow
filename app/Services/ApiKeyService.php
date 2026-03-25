<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function validate(string $token): bool
    {
        return ApiKey::isValid($token);
    }

    public function create(string $name): array
    {
        $plainKey = 'sk_'.Str::random(32);
        $hashedKey = ApiKey::hashKey($plainKey);

        $apiKey = ApiKey::create([
            'name' => $name,
            'key' => $hashedKey,
            'is_active' => true,
        ]);

        return [
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'key' => $plainKey,
            'created_at' => $apiKey->created_at,
        ];
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->update(['is_active' => false]);
    }

    public function activate(ApiKey $apiKey): void
    {
        $apiKey->update(['is_active' => true]);
    }

    public function delete(ApiKey $apiKey): void
    {
        $apiKey->delete();
    }

    public function list(): Collection
    {
        return ApiKey::orderBy('created_at', 'desc')->get();
    }

    public function find(int $id): ?ApiKey
    {
        return ApiKey::find($id);
    }
}
