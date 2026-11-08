<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first()
            ?? static::query()->whereDate('starts_on', '<=', now())->whereDate('ends_on', '>=', now())->first();
    }

    public static function forDate($date): ?self
    {
        return static::query()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
    }
}
