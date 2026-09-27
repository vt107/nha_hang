<?php

namespace App\Services\Ordering;

use App\Exceptions\BusinessException;
use App\Models\Setting;
use App\Models\TableSession;
use Illuminate\Support\Facades\Cache;

/**
 * Giỏ hàng của khách: mỗi điện thoại một giỏ, lưu cache (Redis) theo phiên bàn + device_id, không lưu DB.
 * Mỗi dòng: menu_item_id => ['quantity' => int, 'note' => ?string].
 */
class CartService
{
    private const TTL_HOURS = 12;

    /**
     * @return array<int, array{quantity: int, note: ?string}>
     */
    public function lines(TableSession $session, string $deviceId): array
    {
        return Cache::get($this->key($session, $deviceId), []);
    }

    public function add(TableSession $session, string $deviceId, int $menuItemId, int $quantity = 1): void
    {
        $lines = $this->lines($session, $deviceId);

        $this->setQuantity($session, $deviceId, $menuItemId, ($lines[$menuItemId]['quantity'] ?? 0) + $quantity);
    }

    public function setQuantity(TableSession $session, string $deviceId, int $menuItemId, int $quantity): void
    {
        $lines = $this->lines($session, $deviceId);
        $max = (int) Setting::get('order.max_quantity_per_item', 20);

        if ($quantity > $max) {
            throw new BusinessException("Mỗi món gọi tối đa {$max} phần một lần.");
        }

        if ($quantity <= 0) {
            unset($lines[$menuItemId]);
        } else {
            $lines[$menuItemId] = ['quantity' => $quantity, 'note' => $lines[$menuItemId]['note'] ?? null];
        }

        $this->save($session, $deviceId, $lines);
    }

    public function setNote(TableSession $session, string $deviceId, int $menuItemId, ?string $note): void
    {
        $lines = $this->lines($session, $deviceId);

        if (! isset($lines[$menuItemId])) {
            return;
        }

        $lines[$menuItemId]['note'] = filled($note) ? mb_substr(trim($note), 0, 200) : null;

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
     * @param  array<int, array{quantity: int, note: ?string}>  $lines
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
