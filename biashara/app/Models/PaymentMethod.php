<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'kind', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function isCash(): bool
    {
        return $this->kind === 'cash';
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public static function cash(): self
    {
        return static::query()->where('kind', 'cash')->orderBy('id')->firstOrFail();
    }
}
