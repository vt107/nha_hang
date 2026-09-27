<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessException;

/**
 * Gọi service trong component nhân viên / bếp: lỗi nghiệp vụ hiện thành toast thay vì trang lỗi.
 */
trait RunsBusinessActions
{
    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    protected function attempt(callable $action, ?string $success = null): mixed
    {
        try {
            $result = $action();
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'error');

            return null;
        }

        if ($success) {
            $this->toast($success, 'success');
        }

        return $result;
    }

    protected function toast(string $message, string $type = 'info'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
