<?php

namespace App\Livewire\Concerns;

use App\Models\MenuItem;
use App\Services\Menu\MenuCatalog;
use App\Services\Ordering\OptionResolver;
use Livewire\Attributes\Computed;

/**
 * Bảng chọn size / topping cho món có nhóm tùy chọn (dùng ở trang khách và màn hình phục vụ).
 * View: @include('partials.option-picker'); component tự định nghĩa addConfigured().
 */
trait ConfiguresMenuOptions
{
    public ?int $configuringId = null;

    /** @var array<int, list<int>> option_group_id => option ids */
    public array $selectedOptions = [];

    public int $configQuantity = 1;

    public string $configNote = '';

    #[Computed]
    public function configuringItem(): ?MenuItem
    {
        return $this->configuringId
            ? app(MenuCatalog::class)->categories()->flatMap->menuItems->firstWhere('id', $this->configuringId)
            : null;
    }

    #[Computed]
    public function configuredUnitPrice(): int
    {
        $item = $this->configuringItem;

        if (! $item) {
            return 0;
        }

        return $item->price + $item->optionGroups
            ->flatMap->options
            ->whereIn('id', $this->configuredOptionIds())
            ->sum('price_delta');
    }

    public function startConfiguring(int $menuItemId): void
    {
        $this->configuringId = $menuItemId;
        unset($this->configuringItem);

        if (! $this->configuringItem) {
            $this->configuringId = null;

            return;
        }

        $this->selectedOptions = app(OptionResolver::class)->defaults($this->configuringItem);
        $this->configQuantity = 1;
        $this->configNote = '';
    }

    public function toggleOption(int $groupId, int $optionId): void
    {
        $group = $this->configuringItem?->optionGroups->firstWhere('id', $groupId);
        $option = $group?->options->firstWhere('id', $optionId);

        if (! $option || ! $option->is_available) {
            return;
        }

        $current = $this->selectedOptions[$groupId] ?? [];

        if (in_array($optionId, $current, true)) {
            // Nhóm bắt buộc chọn một: không cho bỏ chọn, chỉ đổi sang lựa chọn khác.
            $this->selectedOptions[$groupId] = $group->isSingle() && $group->isRequired()
                ? $current
                : array_values(array_diff($current, [$optionId]));
        } elseif ($group->isSingle()) {
            $this->selectedOptions[$groupId] = [$optionId];
        } elseif (count($current) < $group->max_select) {
            $this->selectedOptions[$groupId] = [...$current, $optionId];
        } else {
            $this->dispatch('toast', message: "{$group->name}: chọn tối đa {$group->max_select}", type: 'warning');
        }

        unset($this->configuredUnitPrice);
    }

    public function changeConfigQuantity(int $delta): void
    {
        $this->configQuantity = max(1, min(99, $this->configQuantity + $delta));
    }

    public function cancelConfiguring(): void
    {
        $this->reset('configuringId', 'selectedOptions', 'configQuantity', 'configNote');
    }

    /**
     * @return list<int>
     */
    protected function configuredOptionIds(): array
    {
        return array_values(array_map('intval', array_merge([], ...array_values($this->selectedOptions))));
    }
}
