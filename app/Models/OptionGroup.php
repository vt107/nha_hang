<?php

namespace App\Models;

use App\Services\Menu\MenuCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nhóm tùy chọn (Size, Topping...) gắn được cho nhiều món.
 * min_select >= 1 là bắt buộc; max_select = 1 là chọn một.
 */
#[Fillable(['name', 'internal_name', 'min_select', 'max_select', 'sort_order', 'is_active'])]
class OptionGroup extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => MenuCatalog::flush());
        static::deleted(fn () => MenuCatalog::flush());
    }

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }

    public function isSingle(): bool
    {
        return $this->max_select === 1;
    }

    /** "Chọn 1", "Chọn tối đa 3", "Chọn 1-2"... */
    public function selectionHint(): string
    {
        return match (true) {
            $this->isSingle() && $this->isRequired() => 'Bắt buộc, chọn 1',
            $this->isSingle() => 'Không bắt buộc, chọn 1',
            $this->min_select > 0 => "Chọn {$this->min_select}-{$this->max_select}",
            default => "Chọn tối đa {$this->max_select}",
        };
    }

    /**
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<MenuItem, $this>
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)->withPivot('sort_order');
    }
}
