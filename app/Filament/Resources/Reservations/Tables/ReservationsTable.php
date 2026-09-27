<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationStatus;
use App\Exceptions\BusinessException;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('diningTable'))
            ->defaultSort('reserved_at')
            ->columns([
                TextColumn::make('reserved_at')
                    ->label('Thời gian')
                    ->dateTime('H:i d/m/Y')
                    ->sortable()
                    ->description(fn (Reservation $record) => $record->reserved_at->diffForHumans()),
                TextColumn::make('customer_name')
                    ->label('Khách')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Reservation $record) => $record->customer_phone),
                TextColumn::make('customer_phone')
                    ->label('Điện thoại')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('party_size')
                    ->label('Số người')
                    ->suffix(' người'),
                TextColumn::make('diningTable.code')
                    ->label('Bàn')
                    ->badge()
                    ->placeholder('Chưa gán'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge(),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(ReservationStatus::class)
                    ->multiple()
                    ->default([ReservationStatus::Pending->value, ReservationStatus::Confirmed->value]),
                Filter::make('today')
                    ->label('Hôm nay')
                    ->query(fn (Builder $query) => $query->whereDate('reserved_at', today())),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Xác nhận')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Reservation $record) => $record->status === ReservationStatus::Pending)
                    ->action(fn (Reservation $record) => app(ReservationService::class)
                        ->updateStatus($record, ReservationStatus::Confirmed, auth()->user())),
                Action::make('seat')
                    ->label('Khách đã đến')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('primary')
                    ->visible(fn (Reservation $record) => in_array($record->status, ReservationStatus::upcoming(), true))
                    ->requiresConfirmation()
                    ->modalDescription('Mở bàn đã gán cho khách này.')
                    ->action(function (Reservation $record) {
                        try {
                            $session = app(ReservationService::class)->seat($record, auth()->user());
                        } catch (BusinessException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Đã mở {$session->diningTable->displayName()}")->success()->send();
                    }),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('noShow')
                        ->label('Khách không đến')
                        ->icon(Heroicon::OutlinedUserMinus)
                        ->color('danger')
                        ->visible(fn (Reservation $record) => in_array($record->status, ReservationStatus::upcoming(), true))
                        ->requiresConfirmation()
                        ->action(fn (Reservation $record) => app(ReservationService::class)
                            ->updateStatus($record, ReservationStatus::NoShow, auth()->user())),
                    Action::make('cancel')
                        ->label('Hủy đặt bàn')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('gray')
                        ->visible(fn (Reservation $record) => in_array($record->status, ReservationStatus::upcoming(), true))
                        ->requiresConfirmation()
                        ->action(fn (Reservation $record) => app(ReservationService::class)
                            ->updateStatus($record, ReservationStatus::Cancelled, auth()->user())),
                ]),
            ]);
    }
}
