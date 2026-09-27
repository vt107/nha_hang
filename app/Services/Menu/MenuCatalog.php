<?php

namespace App\Services\Menu;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Menu cho khách, cache Redis theo tag "menu". Xóa cache khi admin sửa danh mục / món hoặc bếp báo hết món
 * (model event của Category / MenuItem gọi flush()).
 */
class MenuCatalog
{
    private const TAG = 'menu';

    /**
     * Danh mục đang bật kèm các món đang hiện (gồm cả món tạm hết, để khách thấy nhưng không gọi được).
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        // Cache mảng thuộc tính thô rồi dựng lại model: Laravel không unserialize object từ cache
        // (cache.serializable_classes = false, chống gadget chain khi lộ APP_KEY).
        $rows = Cache::tags(self::TAG)->rememberForever('menu.categories', fn () => Category::query()
            ->active()
            ->ordered()
            ->with(['menuItems' => fn ($query) => $query->visible()->ordered()])
            ->get()
            ->filter(fn (Category $category) => $category->menuItems->isNotEmpty())
            ->map(fn (Category $category) => [
                'category' => $category->getAttributes(),
                'items' => $category->menuItems->map(fn (MenuItem $item) => $item->getAttributes())->all(),
            ])
            ->values()
            ->all());

        return new Collection(array_map(function (array $row) {
            $category = (new Category)->newFromBuilder($row['category']);

            return $category->setRelation('menuItems', MenuItem::hydrate($row['items']));
        }, $rows));
    }

    /**
     * Món gọi được (đang hiện và còn hàng), key theo id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, MenuItem>
     */
    public function orderableItems(array $ids): Collection
    {
        return MenuItem::query()->orderable()->whereKey($ids)->get()->keyBy('id');
    }

    public static function flush(): void
    {
        Cache::tags(self::TAG)->flush();
    }
}
