<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $path
 * @property ProjectStatus $status
 * @property string|null $cli_preference
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|Project active()
 * @method static Builder|Project byPath(string $path)
 * @method static Builder|Project search(string $search)
 * @method static Builder|Project newModelQuery()
 * @method static Builder|Project newQuery()
 * @method static Builder|Project query()
 * @method static Builder|Project whereCreatedAt($value)
 * @method static Builder|Project whereDescription($value)
 * @method static Builder|Project whereId($value)
 * @method static Builder|Project whereName($value)
 * @method static Builder|Project wherePath($value)
 * @method static Builder|Project whereStatus($value)
 * @method static Builder|Project whereUpdatedAt($value)
 * @method static Builder|Project whereCliPreference($value)
 * @method static Builder|Project whereMetadata($value)
 */
class Project extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid7()->toString();
            }
        });
    }

    protected $fillable = [
        'name',
        'description',
        'path',
        'status',
        'cli_preference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'metadata' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', ProjectStatus::Active);
    }

    public function scopeByPath($query, string $path)
    {
        return $query->where('path', $path);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('path', 'like', "%{$search}%");
        });
    }

    public static function validatePathUnique(string $path): ?array
    {
        $existing = self::query()->byPath($path)->first();

        if ($existing !== null) {
            return [
                'exists' => true,
                'project_name' => $existing->name,
                'message' => trans('messages.validation.path_exists', ['name' => $existing->name]),
            ];
        }

        return ['exists' => false, 'message' => null];
    }
}
