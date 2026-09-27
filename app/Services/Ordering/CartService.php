<?php

namespace App\Services\Ordering;

use App\Exceptions\BusinessException;
use App\Models\Setting;
use App\Models\TableSession;
use Illuminate\Support\Facades\Cache;

/**
 * Giỏ hàng của khách: mỗi điện thoại một giỏ, lưu cache (Redis) theo phiên bàn + device_id, không lưu DB.
 * Mỗi dòng là một món + bộ tùy chọn (cùng món khác size là 2 dòng), key = lineKey().
 */
class CartService
{
    private const TTL_HOURS = 12;

    /**
     * @return array<string, array{menu_item_id: int, option_ids: list<int>, quantity: int, note: ?string}>
     */
    public function lines(TableSession $session, string $deviceId): array
    {
        return Cache::get($this->key($session, $deviceId), []);
    }

    /**
     * @param  list<int>  $optionIds
     */
    public function add(TableSession $session, string $deviceId, int $menuItemId, int $quantity = 1, array $optionIds = [], ?string $note = null): string
    {
        $lines = $this->lines($session, $deviceId);
        $key = self::lineKey($menuItemId, $optionIds);
        $quantity += $lines[$key]['quantity'] ?? 0;

        $this->ensureWithinLimit($quantity);

        $lines[$key] = [
            'menu_item_id' => $menuItemId,
            'option_ids' => self::normalizeOptionIds($optionIds),
            'quantity' => $quantity,
            'note' => filled($note) ? mb_substr(trim($note), 0, 200) : ($lines[$key]['note'] ?? null),
        ];

        $this->save($session, $deviceId, $lines);

        return $key;
    }

    public function setQuantity(TableSession $session, string $deviceId, string $key, int $quantity): void
    {
        $lines = $this->lines($session, $deviceId);

        if (! isset($lines[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($lines[$key]);
        } else {
            $this->ensureWithinLimit($quantity);
            $lines[$key]['quantity'] = $quantity;
        }

        $this->save($session, $deviceId, $lines);
    }

    public function setNote(TableSession $session, string $deviceId, string $key, ?string $note): void
    {
        $lines = $this->lines($session, $deviceId);

        if (! isset($lines[$key])) {
            return;
        }

        $lines[$key]['note'] = filled($note) ? mb_substr(trim($note), 0, 200) : null;

        $this->save($session, $deviceId, $lines);
    }

    public function count(TableSession $session, string $deviceId): int
    {
        return array_sum(array_column($this->lines($session, $deviceId), 'quantity'));
    }

    public function clear(TableSession $session, string $deviceId): void
    {
        Cache::forget($this->key($session, $deviceId));
    }

    /**
     * @param  list<int>  $optionIds
     */
    public static function lineKey(int $menuItemId, array $optionIds = []): string
    {
        return $menuItemId.':'.implode('-', self::normalizeOptionIds($optionIds));
    }

    /**
     * @param  list<int|string>  $optionIds
     * @return list<int>
     */
    private static function normalizeOptionIds(array $optionIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $optionIds)));
        sort($ids);

        return $ids;
    }

    private function ensureWithinLimit(int $quantity): void
    {
        $max = (int) Setting::get('order.max_quantity_per_item', 20);

        if ($quantity > $max) {
            throw new BusinessException("Mỗi món gọi tối đa {$max} phần một lần.");
        }
    }

    /**
     * @param  array<string, array{menu_item_id: int, option_ids: list<int>, quantity: int, note: ?string}>  $lines
     */
    private function save(TableSession $session, string $deviceId, array $lines): void
    {
        Cache::put($this->key($session, $deviceId), $lines, now()->addHours(self::TTL_HOURS));
    }

    private function key(TableSession $session, string $deviceId): string
    {
        return "cart:{$session->token}:{$deviceId}";
    }
}
