<?php

namespace App\Models;

use App\Services\Menu\MenuCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['option_group_id', 'name', 'price_delta', 'is_default', 'is_available', 'sort_order'])]
class Option extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'is_default' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => MenuCatalog::flush());
        static::deleted(fn () => MenuCatalog::flush());
    }

    /**
     * @return BelongsTo<OptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
