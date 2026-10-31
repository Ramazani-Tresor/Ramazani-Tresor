<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_sensitive' => 'boolean'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
