<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per group of admin settings, the value as json. Read through a class in App\Support, e.g. CompanyDetails. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function read(string $key): array
    {
        return static::query()->find($key)?->value ?? [];
    }

    public static function write(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
