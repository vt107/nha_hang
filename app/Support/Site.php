<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Cấu hình giao diện / nội dung / SEO mà admin chỉnh ở trang Cài đặt, kèm giá trị mặc định.
 * Dùng trong view qua biến $site (view composer trong AppServiceProvider).
 */
class Site
{
    public const DEFAULT_COLOR = '#d97706';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = Setting::get($key);

        return blank($value) ? $default : $value;
    }

    public function name(): string
    {
        return (string) $this->get('restaurant.name', config('app.name'));
    }

    public function slogan(): ?string
    {
        return $this->get('restaurant.slogan');
    }

    public function logoUrl(): ?string
    {
        return $this->fileUrl('brand.logo');
    }

    public function faviconUrl(): ?string
    {
        return $this->fileUrl('brand.favicon') ?? $this->logoUrl();
    }

    /** Màu chủ đạo dạng #rrggbb (đã kiểm tra hợp lệ). */
    public function color(): string
    {
        $color = strtolower((string) $this->get('brand.color', self::DEFAULT_COLOR));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : self::DEFAULT_COLOR;
    }

    public function hasCustomColor(): bool
    {
        return $this->color() !== self::DEFAULT_COLOR;
    }

    /** Tiêu đề thẻ <title>: "Trang · Tên nhà hàng", trang chủ dùng tiêu đề SEO. */
    public function pageTitle(?string $title = null): string
    {
        return $title ? "{$title} · {$this->name()}" : (string) $this->get('seo.title', $this->name());
    }

    public function description(): ?string
    {
        return $this->get('seo.description', $this->get('home.hero_subtitle'));
    }

    public function shareImageUrl(): ?string
    {
        $url = $this->fileUrl('seo.og_image') ?? $this->fileUrl('home.hero_image') ?? $this->logoUrl();

        return $url ? url($url) : null;
    }

    public function allowsIndexing(): bool
    {
        return (bool) $this->get('seo.allow_indexing', true);
    }

    public function googleAnalyticsId(): ?string
    {
        $id = strtoupper(trim((string) $this->get('seo.google_analytics_id')));

        return preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : null;
    }

    public function qrOrderingEnabled(): bool
    {
        return (bool) $this->get('order.qr_ordering_enabled', true);
    }

    public function reservationsEnabled(): bool
    {
        return (bool) $this->get('reservation.enabled', true);
    }

    /**
     * Mạng xã hội đã điền: nhãn => URL.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = [
            'Facebook' => $this->get('social.facebook'),
            'Zalo' => $this->zaloUrl(),
            'Instagram' => $this->get('social.instagram'),
            'TikTok' => $this->get('social.tiktok'),
            'Google Maps' => $this->get('restaurant.map_url'),
        ];

        return array_filter($links, fn ($url) => is_string($url) && str_starts_with($url, 'http'));
    }

    /**
     * Dữ liệu có cấu trúc schema.org cho Google (trang chủ).
     *
     * @return array<string, mixed>
     */
    public function structuredData(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $this->name(),
            'description' => $this->description(),
            'url' => url('/'),
            'image' => $this->shareImageUrl(),
            'telephone' => $this->get('restaurant.phone'),
            'email' => $this->get('restaurant.email'),
            'address' => $this->get('restaurant.address'),
            'openingHours' => $this->get('restaurant.opening_hours'),
            'servesCuisine' => $this->get('seo.cuisine', 'Việt Nam'),
            'acceptsReservations' => $this->reservationsEnabled(),
            'hasMenu' => url('/#menu'),
            'sameAs' => array_values(array_diff_key($this->socialLinks(), ['Google Maps' => true])) ?: null,
        ]);
    }

    private function zaloUrl(): ?string
    {
        $zalo = trim((string) $this->get('social.zalo'));

        if ($zalo === '') {
            return null;
        }

        // Cho phép nhập số điện thoại: chuyển thành link zalo.me
        return preg_match('/^[0-9 +.]+$/', $zalo) ? 'https://zalo.me/'.preg_replace('/\D/', '', $zalo) : $zalo;
    }

    /** File upload trong storage public → đường dẫn tương đối (/storage/...). */
    private function fileUrl(string $key): ?string
    {
        $path = $this->get($key);

        return is_string($path) && $path !== '' ? '/storage/'.ltrim($path, '/') : null;
    }
}
