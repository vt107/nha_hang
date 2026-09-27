<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Services\Billing\BillingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Hóa đơn chỉ tạo khi nhân viên thu tiền trên màn hình phục vụ; ở đây chỉ xem / in lại.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'hóa đơn';

    protected static ?string $pluralModelLabel = 'Hóa đơn';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'code';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tableSession.diningTable', 'payments', 'cashier', 'voider']))
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Số hóa đơn')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('paid_at')
                    ->label('Thanh toán lúc')
                    ->dateTime('H:i d/m/Y')
                    ->sortable(),
                TextColumn::make('tableSession.diningTable.code')
                    ->label('Bàn')
                    ->badge(),
                TextColumn::make('subtotal')
                    ->label('Tiền món')
                    ->money('VND', locale: 'vi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_amount')
                    ->label('Giảm giá')
                    ->money('VND', locale: 'vi')
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Tổng thu')
                    ->money('VND', locale: 'vi')
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Sum::make()->label('Tổng')->money('VND', locale: 'vi')),
                TextColumn::make('payments.method')
                    ->label('Hình thức')
                    ->badge(),
                TextColumn::make('cashier.name')
                    ->label('Thu ngân')
                    ->placeholder('Tự động (CK)')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->description(fn (Invoice $record) => $record->void_reason ? 'Lý do: '.$record->void_reason : null),
            ])
            ->filters([
                Filter::make('paid_at')
                    ->label('Ngày thanh toán')
                    ->schema([
                        DatePicker::make('from')->label('Từ ngày')->native(false)->displayFormat('d/m/Y')->default(today()),
                        DatePicker::make('to')->label('Đến ngày')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->where('paid_at', '>=', $date.' 00:00:00'))
                        ->when($data['to'] ?? null, fn (Builder $query, $date) => $query->where('paid_at', '<=', $date.' 23:59:59')))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'Từ '.date('d/m/Y', strtotime($data['from'])) : null,
                        ($data['to'] ?? null) ? 'Đến '.date('d/m/Y', strtotime($data['to'])) : null,
                    ])),
                SelectFilter::make('method')
                    ->label('Hình thức')
                    ->options(PaymentMethod::class)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $method) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $method)),
                    )),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                Action::make('void')
                    ->label('Hủy')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Paid
                        && auth()->user()->hasRole(UserRole::Admin, UserRole::Manager))
                    ->modalHeading(fn (Invoice $record) => "Hủy hóa đơn {$record->code}")
                    ->modalDescription('Hóa đơn bị hủy không tính vào doanh thu. Thao tác được ghi lại người hủy và lý do.')
                    ->schema([
                        TextInput::make('reason')
                            ->label('Lý do hủy')
                            ->required()
                            ->maxLength(250),
                        Toggle::make('reopen')
                            ->label('Mở lại bàn để thu lại tiền')
                            ->helperText('Bật: sai giảm giá / hình thức thanh toán, cần thu lại. Tắt: hoàn tiền / miễn phí cho khách.')
                            ->default(true),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        try {
                            app(BillingService::class)->void($record, auth()->user(), $data['reason'], (bool) $data['reopen']);
                        } catch (BusinessException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Đã hủy hóa đơn {$record->code}")->success()->send();
                    }),
                Action::make('print')
                    ->label('In lại')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Invoice $record) => route('staff.invoices.print', $record), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
