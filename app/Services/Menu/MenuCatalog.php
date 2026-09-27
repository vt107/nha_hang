<?php

namespace App\Services\Menu;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Menu cho khách, cache Redis theo tag "menu". Xóa cache khi admin sửa danh mục / món / tùy chọn hoặc bếp báo hết món
 * (model event của Category / MenuItem / OptionGroup / Option gọi flush()).
 */
class MenuCatalog
{
    private const TAG = 'menu';

    /**
     * Danh mục đang bật kèm các món đang hiện (gồm cả món tạm hết, để khách thấy nhưng không gọi được)
     * và nhóm tùy chọn của từng món.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        // Cache mảng thuộc tính thô rồi dựng lại model: Laravel không unserialize object từ cache
        // (cache.serializable_classes = false, chống gadget chain khi lộ APP_KEY).
        $data = Cache::tags(self::TAG)->rememberForever('menu.catalog', fn () => [
            'groups' => OptionGroup::query()
                ->where('is_active', true)
                ->with('options')
                ->get()
                ->mapWithKeys(fn (OptionGroup $group) => [$group->id => [
                    'group' => $group->getAttributes(),
                    'options' => $group->options->map(fn (Option $option) => $option->getAttributes())->all(),
                ]])
                ->all(),
            'categories' => Category::query()
                ->active()
                ->ordered()
                ->with(['menuItems' => fn ($query) => $query->visible()->ordered()->with('optionGroups:id')])
                ->get()
                ->filter(fn (Category $category) => $category->menuItems->isNotEmpty())
                ->map(fn (Category $category) => [
                    'category' => $category->getAttributes(),
                    'items' => $category->menuItems->map(fn (MenuItem $item) => [
                        'item' => $item->getAttributes(),
                        'group_ids' => $item->optionGroups->pluck('id')->all(),
                    ])->all(),
                ])
                ->values()
                ->all(),
        ]);

        // hydrate() (không phải new + newFromBuilder) để model mang connection như khi đọc từ DB.
        $groups = collect($data['groups'])->map(fn (array $row) => OptionGroup::hydrate([$row['group']])->first()
            ->setRelation('options', Option::hydrate($row['options'])));

        $categories = Category::hydrate(array_column($data['categories'], 'category'));

        foreach ($data['categories'] as $index => $row) {
            $items = MenuItem::hydrate(array_column($row['items'], 'item'));

            foreach ($row['items'] as $itemIndex => $itemRow) {
                $items[$itemIndex]->setRelation('optionGroups', new Collection(
                    collect($itemRow['group_ids'])->map(fn (int $id) => $groups->get($id))->filter()->values()->all(),
                ));
            }

            $categories[$index]->setRelation('menuItems', $items);
        }

        return $categories;
    }

    /**
     * Món gọi được (đang hiện và còn hàng) đọc thẳng DB kèm nhóm tùy chọn, key theo id: dùng khi đặt món.
     *
     * @param  list<int>  $ids
     * @return Collection<int, MenuItem>
     */
    public function orderableItems(array $ids): Collection
    {
        return MenuItem::query()
            ->orderable()
            ->whereKey($ids)
            ->with(['optionGroups' => fn ($query) => $query->where('is_active', true)->with('options')])
            ->get()
            ->keyBy('id');
    }

    public static function flush(): void
    {
        Cache::tags(self::TAG)->flush();
    }
}
