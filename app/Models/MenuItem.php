<?php

namespace App\Models;

use App\Services\Menu\MenuCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * is_active: admin ẩn / hiện món trên menu. is_available: còn / hết món (bếp bật tắt trong ngày).
 */
#[Fillable(['category_id', 'name', 'slug', 'description', 'image', 'price', 'is_available', 'is_active', 'is_featured', 'sort_order'])]
class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Đường dẫn tương đối để ảnh hiện đúng dù khách truy cập qua IP LAN hay domain.
     *
     * @return Attribute<?string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? '/storage/'.ltrim($this->image, '/') : null);
    }

    protected static function booted(): void
    {
        static::saved(fn () => MenuCatalog::flush());
        static::deleted(fn () => MenuCatalog::flush());
        static::restored(fn () => MenuCatalog::flush());
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<OptionGroup, $this>
     */
    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** Món hiện trên menu khách: món và danh mục đều đang bật. */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    #[Scope]
    protected function orderable(Builder $query): void
    {
        $query->visible()->where($query->qualifyColumn('is_available'), true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy($query->qualifyColumn('sort_order'))->orderBy($query->qualifyColumn('id'));
    }
}
