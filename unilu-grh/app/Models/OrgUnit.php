<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgUnit extends Model
{
    public const TYPES = [
        'rectorate' => 'Rectorat', 'vice_rectorate' => 'Vice-rectorat', 'sga' => 'Secrétariat général académique',
        'sgad' => 'Secrétariat général administratif', 'faculty' => 'Faculté', 'department' => 'Département',
        'research_center' => 'Centre de recherche', 'library' => 'Bibliothèque', 'admin_service' => 'Service administratif',
        'technical_service' => 'Service technique', 'other' => 'Autre unité',
    ];

    private static ?array $tree = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$tree = null);
    }

    public function parent()
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrgUnit::class, 'parent_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Identifiants de l'unité et de toutes ses sous-unités. */
    public static function subtreeIds(int $id): array
    {
        if (self::$tree === null) {
            self::$tree = [];
            foreach (static::query()->get(['id', 'parent_id']) as $u) {
                self::$tree[(int) $u->parent_id][] = (int) $u->id;
            }
        }
        $ids = [$id];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            foreach (self::$tree[$cur] ?? [] as $child) {
                if (! in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $stack[] = $child;
                }
            }
        }

        return $ids;
    }

    public static function flushTree(): void
    {
        self::$tree = null;
    }

    /** Faculté (ou unité de premier niveau) à laquelle appartient l'unité. */
    public function faculty(): ?OrgUnit
    {
        $u = $this;
        $guard = 0;
        while ($u && $u->type !== 'faculty' && $u->parent_id && $guard++ < 20) {
            $u = $u->parent;
        }

        return $u?->type === 'faculty' ? $u : null;
    }

    public function pathName(): string
    {
        $names = [$this->name];
        $u = $this->parent;
        $guard = 0;
        while ($u && $guard++ < 20) {
            array_unshift($names, $u->name);
            $u = $u->parent;
        }

        return implode(' › ', $names);
    }
}
