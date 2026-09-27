<?php

namespace App\Filament\Resources\BankTransactions;

use App\Enums\BankTransactionStatus;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\BankTransaction;
use App\Models\TableSession;
use App\Services\Billing\BankTransferService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tiền chuyển khoản báo về qua webhook SePay. Giao dịch không khớp bàn thì gán tay ở đây.
 */
class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'giao dịch';

    protected static ?string $pluralModelLabel = 'Chuyển khoản';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::query()
            ->whereIn('status', [BankTransactionStatus::Matched, BankTransactionStatus::Unmatched])
            ->where('created_at', '>=', today())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tableSession.diningTable', 'invoice']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('transacted_at')
                    ->label('Thời gian')
                    ->dateTime('H:i d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', locale: 'vi')
                    ->weight('bold'),
                TextColumn::make('content')
                    ->label('Nội dung')
                    ->limit(50)
                    ->tooltip(fn (BankTransaction $record) => $record->content)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->description(fn (BankTransaction $record) => $record->note),
                TextColumn::make('tableSession.diningTable.code')
                    ->label('Bàn')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('invoice.code')
                    ->label('Hóa đơn')
                    ->placeholder('—'),
                TextColumn::make('reference_code')
                    ->label('Mã GD')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(BankTransactionStatus::class),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Gán cho bàn')
                    ->icon(Heroicon::OutlinedLink)
                    ->visible(fn (BankTransaction $record) => $record->status === BankTransactionStatus::Unmatched)
                    ->schema([
                        Select::make('table_session_id')
                            ->label('Bàn đang phục vụ')
                            ->options(fn () => TableSession::query()->open()->with('diningTable')->get()
                                ->mapWithKeys(fn (TableSession $session) => [$session->id => "{$session->diningTable->code} · {$session->code}"]))
                            ->required(),
                    ])
                    ->action(function (BankTransaction $record, array $data) {
                        $result = app(BankTransferService::class)->apply($record, TableSession::findOrFail($data['table_session_id']));

                        Notification::make()
                            ->title($result->status === BankTransactionStatus::Applied ? 'Đã thu tiền và đóng bàn' : 'Đã gán cho bàn: '.$result->note)
                            ->success()
                            ->send();
                    }),
                Action::make('ignore')
                    ->label('Bỏ qua')
                    ->color('gray')
                    ->visible(fn (BankTransaction $record) => in_array($record->status, [BankTransactionStatus::Unmatched, BankTransactionStatus::Matched], true))
                    ->requiresConfirmation()
                    ->action(fn (BankTransaction $record) => $record->update([
                        'status' => BankTransactionStatus::Ignored,
                        'note' => 'Bỏ qua bởi '.auth()->user()->name,
                        'handled_by' => auth()->id(),
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
        ];
    }
}
