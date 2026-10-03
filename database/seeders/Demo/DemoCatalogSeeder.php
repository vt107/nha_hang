<?php

namespace Database\Seeders\Demo;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bản demo: bổ sung cài đặt, nhân viên, thực đơn đầy đủ (món tạm hết / ẩn / ngừng bán, size / topping),
 * khu vực VIP, bàn tạm ngưng. Chạy sau các seeder gốc (Setting / User / DiningTable / Menu / Option).
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->users();
        $this->menu();
        $this->options();
        $this->tables();
    }

    private function settings(): void
    {
        $values = [
            'restaurant.email' => 'lienhe@nhahang.test',
            'restaurant.opening_hours' => '10:00 - 22:00 (cả tuần)',
            'home.hero_eyebrow' => 'Chào mừng đến với',
            'home.hero_subtitle' => 'Món Việt ba miền nấu mỗi ngày từ nguyên liệu chợ sáng. Quét QR tại bàn để gọi món, không cần chờ.',
            'home.cta_text' => 'Đặt bàn ngay',
            'home.about_title' => 'Về chúng tôi',
            'home.about_text' => "Nhà hàng gia đình 3 tầng với sân vườn và phòng riêng cho tiệc tới 12 khách.\nThực đơn hơn 40 món: phở, bún chả, lẩu, hải sản tươi và đồ uống pha chế tại quầy.",
            'menu.welcome_message' => 'Chúc quý khách ngon miệng! Wifi: NhaHangDemo / 12345678',
            'invoice.header_note' => 'Wifi: NhaHangDemo / 12345678',
            'invoice.footer_note' => 'Cảm ơn quý khách, hẹn gặp lại!',
            'reservation.form_note' => 'Nhà hàng giữ bàn 15 phút. Nhóm trên 20 người vui lòng gọi điện.',
            'seo.description' => 'Nhà hàng món Việt: gọi món bằng QR tại bàn, đặt bàn online, phòng riêng cho tiệc.',
            // Bản demo không cần Google index.
            'seo.allow_indexing' => false,
            // Hóa đơn demo có phí phục vụ + VAT để thấy đủ các dòng.
            'billing.service_charge_percent' => 5,
            'billing.vat_percent' => 8,
        ];

        foreach ($values as $name => $value) {
            Setting::set($name, $value);
        }
    }

    private function users(): void
    {
        $base = [
            'admin@nhahang.test' => ['name' => 'Trần Minh Quân', 'phone' => '0901 234 101'],
            'manager@nhahang.test' => ['name' => 'Lê Thu Hà', 'phone' => '0901 234 102'],
            'waiter@nhahang.test' => ['name' => 'Nguyễn Văn Tú', 'phone' => '0901 234 103'],
            'kitchen@nhahang.test' => ['name' => 'Phạm Đức Anh', 'phone' => '0901 234 104'],
        ];

        foreach ($base as $email => $attributes) {
            User::query()->where('email', $email)->update([...$attributes, 'last_login_at' => now()->subHours(random_int(1, 20))]);
        }

        $extra = [
            ['name' => 'Hoàng Thị Mai', 'email' => 'mai.hoang@nhahang.test', 'role' => UserRole::Waiter, 'phone' => '0901 234 105'],
            ['name' => 'Đỗ Quang Huy', 'email' => 'huy.do@nhahang.test', 'role' => UserRole::Waiter, 'phone' => '0901 234 106'],
            ['name' => 'Vũ Thị Hồng', 'email' => 'hong.vu@nhahang.test', 'role' => UserRole::Kitchen, 'phone' => '0901 234 107'],
            ['name' => 'Bùi Văn Nam', 'email' => 'nam.bui@nhahang.test', 'role' => UserRole::Waiter, 'phone' => '0901 234 108', 'is_active' => false],
            ['name' => 'Ngô Thanh Tùng', 'email' => 'tung.ngo@nhahang.test', 'role' => UserRole::Waiter, 'phone' => '0901 234 109', 'deleted' => true],
        ];

        foreach ($extra as $row) {
            $user = User::firstOrCreate(['email' => $row['email']], [
                ...collect($row)->except('deleted')->all(),
                'password' => 'password',
                'email_verified_at' => now()->subMonths(4),
            ]);
            $user->forceFill(['last_login_at' => ($row['is_active'] ?? true) ? now()->subHours(random_int(2, 48)) : now()->subDays(25)])->save();

            if ($row['deleted'] ?? false) {
                $user->delete();
            }
        }
    }

    private function menu(): void
    {
        // [danh mục => [tên, giá, mô tả, cờ]]: cờ featured / unavailable (tạm hết) / hidden (admin ẩn) / deleted (ngừng bán)
        $menu = [
            'Khai vị' => [
                ['Gỏi cuốn tôm thịt', 45000, 'Bánh tráng cuốn tôm, thịt ba chỉ, bún, rau thơm, chấm tương đậu phộng.'],
                ['Chả giò rế', 55000, 'Chả giò vỏ rế giòn rụm, nhân thịt heo, tôm, khoai môn.'],
                ['Gỏi ngó sen tôm thịt', 85000, 'Ngó sen giòn trộn tôm, thịt, cà rốt, nước mắm chua ngọt.'],
                ['Súp cua', 45000, 'Súp cua trứng bắc thảo, nấm tuyết, nóng hổi.'],
                ['Nem chua rán', 55000, 'Nem chua Hà Nội chiên xù, chấm tương ớt.'],
                ['Khoai tây chiên', 40000, 'Khoai tây chiên giòn lắc phô mai.'],
            ],
            'Món chính' => [
                ['Phở bò tái nạm', 65000, 'Nước dùng ninh xương 12 tiếng, bò tái nạm, hành ngò.', ['featured']],
                ['Cơm tấm sườn bì chả', 60000, 'Sườn nướng than hồng, bì, chả trứng, mỡ hành.'],
                ['Bún chả Hà Nội', 60000, 'Chả viên, chả miếng nướng than, bún tươi, nước chấm đu đủ.'],
                ['Cá kho tộ', 120000, 'Cá basa kho tộ đất với tiêu xanh, nước màu dừa.'],
                ['Gà nướng mật ong', 180000, 'Nửa con gà ta nướng mật ong, kèm xôi chiên.', ['featured']],
                ['Bò lúc lắc', 145000, 'Thăn bò Úc xào lửa lớn với ớt chuông, hành tây, khoai tây chiên.', ['featured']],
                ['Mì xào bò', 75000, 'Mì trứng xào giòn với bò, cải ngọt, cà chua.'],
                ['Cơm chiên hải sản', 85000, 'Cơm chiên tôm, mực, trứng, đậu Hà Lan.'],
                ['Bún bò Huế', 65000, 'Tạm ngưng bán để đổi công thức.', ['hidden']],
                ['Cơm gà xối mỡ', 65000, 'Đã ngừng bán.', ['deleted']],
            ],
            'Rau & canh' => [
                ['Rau muống xào tỏi', 45000, 'Rau muống xanh giòn xào tỏi phi.'],
                ['Cải thìa xào nấm', 50000, 'Cải thìa, nấm đông cô xào dầu hào.'],
                ['Canh chua cá lóc', 95000, 'Canh chua miền Tây với cá lóc đồng, bạc hà, đậu bắp.'],
            ],
            'Hải sản' => [
                ['Tôm sú nướng muối ớt', 220000, 'Tôm sú size 20 con/kg nướng muối ớt (500g).', ['featured']],
                ['Mực chiên nước mắm', 165000, 'Mực ống chiên giòn sốt nước mắm tỏi.'],
                ['Nghêu hấp sả', 95000, 'Nghêu Bến Tre hấp sả, lá chanh.', ['unavailable']],
            ],
            'Lẩu' => [
                ['Lẩu thái hải sản', 350000, 'Tôm, mực, nghêu, cá viên, nấm, nước lẩu chua cay.', ['featured']],
                ['Lẩu gà lá é', 320000, 'Gà ta, lá é Đà Lạt, măng chua, bún tươi.'],
                ['Lẩu bò nhúng giấm', 380000, 'Bò bắp hoa nhúng giấm dừa, cuốn bánh tráng rau sống.'],
            ],
            'Đồ uống' => [
                ['Trà đá', 5000, 'Trà đá miễn phí châm thêm.'],
                ['Cà phê sữa đá', 30000, 'Cà phê phin Robusta, sữa đặc.'],
                ['Nước cam ép', 40000, 'Cam sành vắt tại quầy.'],
                ['Bia Sài Gòn', 25000, 'Bia Sài Gòn Special lon 330ml.'],
                ['Trà tắc', 25000, 'Trà xanh, tắc tươi, mật ong.'],
                ['Sinh tố bơ', 45000, 'Bơ sáp Đắk Lắk xay sữa đặc.', ['unavailable']],
                ['Nước dừa tươi', 35000, 'Dừa xiêm Bến Tre nguyên trái.'],
                ['Bia Heineken', 35000, 'Heineken lon 330ml.'],
                ['Coca-Cola', 20000, 'Lon 330ml.'],
            ],
            'Tráng miệng' => [
                ['Chè khúc bạch', 35000, 'Khúc bạch phô mai, nhãn, hạnh nhân lát.', ['featured']],
                ['Bánh flan', 20000, 'Flan trứng gà ta, caramel đắng nhẹ.'],
                ['Chè thái', 35000, 'Mít, sầu riêng, thạch, nước cốt dừa.'],
                ['Trái cây thập cẩm', 60000, 'Dĩa trái cây theo mùa.'],
            ],
            // Danh mục admin tạm ẩn (chỉ thấy trong trang quản trị).
            'Món Tết' => [
                ['Bánh chưng nếp cẩm', 120000, 'Chỉ bán dịp Tết.'],
                ['Giò thủ', 90000, 'Chỉ bán dịp Tết.'],
            ],
        ];

        $categoryOrder = 0;

        foreach ($menu as $categoryName => $items) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'sort_order' => $categoryOrder],
            );
            $category->update([
                'sort_order' => $categoryOrder++,
                'is_active' => $categoryName !== 'Món Tết',
                'description' => $categoryName === 'Món Tết' ? 'Mở lại dịp Tết Nguyên đán.' : null,
            ]);

            foreach ($items as $index => $row) {
                [$name, $price, $description] = $row;
                $flags = $row[3] ?? [];

                $item = MenuItem::withTrashed()->firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['category_id' => $category->id, 'name' => $name, 'price' => $price],
                );
                $item->update([
                    'category_id' => $category->id,
                    'price' => $price,
                    'description' => $description,
                    'sort_order' => $index,
                    'is_featured' => in_array('featured', $flags, true),
                    'is_available' => ! in_array('unavailable', $flags, true),
                    'is_active' => ! in_array('hidden', $flags, true),
                ]);

                if (in_array('deleted', $flags, true) && ! $item->trashed()) {
                    $item->delete();
                }
            }
        }
    }

    private function options(): void
    {
        // [nhóm, [tùy chọn: tên, giá cộng thêm, mặc định, còn hàng], món áp dụng]
        $groups = [
            [
                'group' => ['name' => 'Size', 'internal_name' => 'Size - đồ uống', 'min_select' => 1, 'max_select' => 1],
                'options' => [['Vừa (M)', 0, true], ['Lớn (L)', 10000, false]],
                'items' => ['Cà phê sữa đá', 'Nước cam ép', 'Trà tắc', 'Sinh tố bơ'],
            ],
            [
                'group' => ['name' => 'Đá', 'internal_name' => 'Mức đá', 'min_select' => 0, 'max_select' => 1],
                'options' => [['Ít đá', 0, false], ['Không đá', 0, false], ['Đá riêng', 0, false]],
                'items' => ['Cà phê sữa đá', 'Nước cam ép', 'Trà tắc', 'Sinh tố bơ'],
            ],
            [
                'group' => ['name' => 'Độ ngọt', 'internal_name' => 'Mức đường', 'min_select' => 0, 'max_select' => 1],
                'options' => [['Ít ngọt', 0, false], ['Ngọt vừa', 0, false], ['Ngọt nhiều', 0, false]],
                'items' => ['Cà phê sữa đá', 'Trà tắc', 'Sinh tố bơ'],
            ],
            [
                'group' => ['name' => 'Thêm', 'internal_name' => 'Topping phở', 'min_select' => 0, 'max_select' => 3],
                'options' => [['Trứng trần', 10000, false], ['Thêm bánh phở', 10000, false], ['Thêm thịt bò', 25000, false, false]],
                'items' => ['Phở bò tái nạm'],
            ],
            [
                'group' => ['name' => 'Ăn kèm', 'internal_name' => 'Món kèm cơm', 'min_select' => 0, 'max_select' => 2],
                'options' => [['Trứng ốp la', 10000, false], ['Thêm chả trứng', 15000, false], ['Thêm cơm', 10000, false]],
                'items' => ['Cơm tấm sườn bì chả', 'Cơm chiên hải sản'],
            ],
            [
                'group' => ['name' => 'Độ cay', 'internal_name' => 'Độ cay - lẩu', 'min_select' => 1, 'max_select' => 1],
                'options' => [['Không cay', 0, false], ['Cay vừa', 0, true], ['Rất cay', 0, false]],
                'items' => ['Lẩu thái hải sản', 'Lẩu gà lá é', 'Mì xào bò'],
            ],
            [
                'group' => ['name' => 'Cỡ nồi', 'internal_name' => 'Size lẩu', 'min_select' => 1, 'max_select' => 1],
                'options' => [['Nồi vừa (2-3 người)', 0, true], ['Nồi lớn (4-6 người)', 150000, false]],
                'items' => ['Lẩu thái hải sản', 'Lẩu gà lá é', 'Lẩu bò nhúng giấm'],
            ],
            [
                'group' => ['name' => 'Topping', 'internal_name' => 'Topping chè', 'min_select' => 0, 'max_select' => 2],
                'options' => [['Thạch dừa', 5000, false], ['Trân châu', 5000, false], ['Thêm nước cốt dừa', 5000, false]],
                'items' => ['Chè khúc bạch', 'Chè thái'],
            ],
            // Nhóm admin đã tắt: không hiện trên menu khách.
            [
                'group' => ['name' => 'Kèm bánh hỏi', 'internal_name' => 'Bánh hỏi (ngưng)', 'min_select' => 0, 'max_select' => 1, 'is_active' => false],
                'options' => [['Bánh hỏi', 15000, false]],
                'items' => ['Gà nướng mật ong'],
            ],
        ];

        foreach ($groups as $order => $row) {
            $group = OptionGroup::firstOrCreate(['internal_name' => $row['group']['internal_name']], [...$row['group'], 'sort_order' => $order]);
            $group->update([...$row['group'], 'sort_order' => $order]);

            foreach ($row['options'] as $index => $option) {
                [$name, $price, $default] = $option;
                $group->options()->updateOrCreate(['name' => $name], [
                    'price_delta' => $price,
                    'is_default' => $default,
                    'is_available' => $option[3] ?? true,
                    'sort_order' => $index,
                ]);
            }

            $itemIds = MenuItem::query()->whereIn('name', $row['items'])->pluck('id');
            $group->menuItems()->syncWithoutDetaching($itemIds->mapWithKeys(fn (int $id) => [$id => ['sort_order' => $order]])->all());
        }
    }

    private function tables(): void
    {
        $vip = Area::firstOrCreate(['name' => 'Phòng VIP'], ['sort_order' => 3]);

        foreach ([['P01', 'Phòng Sen'], ['P02', 'Phòng Lan']] as $index => [$code, $name]) {
            $vip->diningTables()->firstOrCreate(['code' => $code], ['name' => $name, 'capacity' => 12, 'sort_order' => $index + 1]);
        }

        // Bàn tạm ngưng (admin tắt) và bàn đã xóa (khôi phục được trong admin).
        DiningTable::query()->where('code', 'S04')->update(['is_active' => false, 'name' => 'Bàn S04 (đang sửa dù)']);

        $removed = DiningTable::withTrashed()->firstOrCreate(
            ['code' => 'A09'],
            ['area_id' => Area::query()->where('name', 'Tầng 1')->value('id'), 'capacity' => 2, 'sort_order' => 9],
        );
        if (! $removed->trashed()) {
            $removed->delete();
        }

        // Bàn demo cho khu vực "Khách tại bàn": mã QR cố định (config/demo.php).
        DiningTable::query()->where('code', 'A03')->firstOrFail()->forceFill(['qr_token' => config('demo.qr_table_token')])->save();
    }
}
