<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid7()->toString();
            }
        });
    }

    protected $fillable = [
        'name',
        'key',
        'is_active',
    ];

    protected $hidden = [
        'key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function isValid(string $plainKey): bool
    {
        $apiKey = static::active()
            ->where('key', hash('sha256', $plainKey))
            ->first();

        return $apiKey !== null;
    }

    public static function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }
}
