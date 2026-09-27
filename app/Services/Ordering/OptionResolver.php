<?php

namespace App\Services\Ordering;

use App\Exceptions\BusinessException;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;

/**
 * Kiểm tra tùy chọn khách chọn cho một món (đúng nhóm, đủ số lượng tối thiểu / tối đa, còn hàng)
 * và tính giá. Món phải được nạp sẵn optionGroups.options.
 */
class OptionResolver
{
    /**
     * @param  list<int>  $optionIds
     * @return array{options: list<array{group: string, name: string, price_delta: int}>, extra: int}
     */
    public function resolve(MenuItem $item, array $optionIds): array
    {
        $selected = collect($optionIds)->map(fn ($id) => (int) $id)->unique();
        $snapshot = [];
        $matched = [];

        foreach ($item->optionGroups as $group) {
            /** @var OptionGroup $group */
            $chosen = $group->options->whereIn('id', $selected->all());

            if ($unavailable = $chosen->firstWhere('is_available', false)) {
                throw new BusinessException("Tùy chọn \"{$unavailable->name}\" của {$item->name} đã hết.");
            }

            if ($chosen->count() < $group->min_select) {
                throw new BusinessException("Vui lòng chọn {$group->name} cho {$item->name}.");
            }

            if ($chosen->count() > $group->max_select) {
                throw new BusinessException("{$item->name}: {$group->name} chọn tối đa {$group->max_select}.");
            }

            foreach ($chosen as $option) {
                /** @var Option $option */
                $matched[] = $option->id;
                $snapshot[] = ['group' => $group->name, 'name' => $option->name, 'price_delta' => $option->price_delta];
            }
        }

        if ($selected->diff($matched)->isNotEmpty()) {
            throw new BusinessException("Tùy chọn của {$item->name} không hợp lệ, vui lòng chọn lại.");
        }

        return [
            'options' => $snapshot,
            'extra' => array_sum(array_column($snapshot, 'price_delta')),
        ];
    }

    /**
     * Tùy chọn mặc định khi mở bảng chọn: option is_default, nhóm bắt buộc chọn một thì lấy option còn hàng đầu tiên.
     *
     * @return array<int, list<int>> option_group_id => option ids
     */
    public function defaults(MenuItem $item): array
    {
        $selected = [];

        foreach ($item->optionGroups as $group) {
            $available = $group->options->where('is_available', true);
            $ids = $available->where('is_default', true)->pluck('id')->take($group->max_select)->values()->all();

            if ($ids === [] && $group->isRequired() && $group->isSingle() && $available->isNotEmpty()) {
                $ids = [$available->first()->id];
            }

            $selected[$group->id] = $ids;
        }

        return $selected;
    }
}
