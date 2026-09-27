<?php

namespace App\Filament\Pages;

use App\Enums\OrderConfirmMode;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Support\VietQr;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Cấu hình vận hành lưu bảng settings, key "group.key" (form dùng state lồng nhau group → key).
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Cài đặt';

    protected static ?string $title = 'Cài đặt';

    protected static ?string $slug = 'settings';

    private const INTEGER_SETTINGS = [
        'order.max_quantity_per_item',
        'kitchen.warn_after_minutes',
        'billing.service_charge_percent',
        'billing.vat_percent',
        'reservation.max_party_size',
        'reservation.default_duration_minutes',
        'reservation.min_lead_minutes',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Admin;
    }

    public function mount(): void
    {
        $values = [];

        foreach (Setting::all() as $setting) {
            Arr::set($values, "{$setting->group}.{$setting->key}", $setting->value);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->statePath('data')
            ->components([
                Section::make('Nhà hàng')
                    ->columns(2)
                    ->schema([
                        TextInput::make('restaurant.name')->label('Tên nhà hàng')->required()->maxLength(100),
                        TextInput::make('restaurant.phone')->label('Điện thoại')->maxLength(30),
                        TextInput::make('restaurant.address')->label('Địa chỉ')->maxLength(255)->columnSpanFull(),
                        TextInput::make('restaurant.opening_hours')->label('Giờ mở cửa')->placeholder('10:00 - 22:00')->maxLength(100),
                    ]),
                Section::make('Gọi món qua QR')
                    ->schema([
                        Select::make('order.confirm_mode')
                            ->label('Nhân viên duyệt order của khách')
                            ->options(OrderConfirmMode::class)
                            ->required()
                            ->native(false),
                        TextInput::make('order.max_quantity_per_item')
                            ->label('Số phần tối đa mỗi món / lần gọi')
                            ->integer()->minValue(1)->maxValue(100)->required(),
                        TextInput::make('kitchen.warn_after_minutes')
                            ->label('Bếp: cảnh báo món chờ quá')
                            ->integer()->minValue(1)->suffix('phút')->required(),
                    ]),
                Section::make('Hóa đơn')
                    ->columns(2)
                    ->schema([
                        TextInput::make('billing.service_charge_percent')
                            ->label('Phí phục vụ')
                            ->integer()->minValue(0)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('billing.vat_percent')
                            ->label('VAT')
                            ->integer()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    ]),
                Section::make('Chuyển khoản (VietQR)')
                    ->description('Khách quét mã VietQR trên trang thanh toán. Có webhook SePay thì hệ thống tự xác nhận, không thì nhân viên kiểm tra tiền về rồi xác nhận.')
                    ->columns(2)
                    ->schema([
                        Select::make('bank.bin')
                            ->label('Ngân hàng')
                            ->options(VietQr::BANKS)
                            ->searchable()
                            ->required(),
                        TextInput::make('bank.account_number')
                            ->label('Số tài khoản')
                            ->required()
                            ->regex('/^[0-9A-Za-z]{4,19}$/'),
                        TextInput::make('bank.account_name')
                            ->label('Chủ tài khoản')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                        Toggle::make('bank.auto_confirm')
                            ->label('Tự xác nhận chuyển khoản qua webhook SePay')
                            ->helperText(fn () => 'Tiền về đúng số tiền + đúng mã bàn thì tự thu và đóng bàn. Webhook URL: '.url('/webhooks/sepay').(config('services.sepay.webhook_key') ? '' : ' (chưa đặt SEPAY_WEBHOOK_KEY trong .env)'))
                            ->columnSpanFull(),
                    ]),
                Section::make('Đặt bàn online')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('reservation.max_party_size')
                            ->label('Số người tối đa / lượt đặt')
                            ->integer()->minValue(1)->required(),
                        TextInput::make('reservation.default_duration_minutes')
                            ->label('Thời lượng giữ bàn')
                            ->integer()->minValue(30)->suffix('phút')->required(),
                        TextInput::make('reservation.min_lead_minutes')
                            ->label('Đặt trước tối thiểu')
                            ->integer()->minValue(0)->suffix('phút')->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Lưu cài đặt')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        foreach (Arr::dot($this->form->getState()) as $name => $value) {
            Setting::set($name, in_array($name, self::INTEGER_SETTINGS, true) ? (int) $value : $value);
        }

        Notification::make()->title('Đã lưu cài đặt')->success()->send();
    }
}
