<?php

namespace App\Filament\Resources\DiningTables\Tables;

use App\Models\Area;
use App\Models\DiningTable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class DiningTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['area', 'openSession']))
            ->defaultSort('sort_order')
            ->defaultGroup(Group::make('area.name')
                ->label('Khu vực')
                ->orderQueryUsing(fn ($query, string $direction) => $query->orderBy(
                    Area::select('sort_order')->whereColumn('areas.id', 'dining_tables.area_id'),
                    $direction,
                )))
            ->columns([
                TextColumn::make('code')
                    ->label('Mã bàn')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Tên hiển thị')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('capacity')
                    ->label('Số ghế')
                    ->suffix(' ghế'),
                TextColumn::make('current_status')
                    ->label('Hiện tại')
                    ->badge()
                    ->state(fn (DiningTable $record) => $record->openSession?->status->getLabel() ?? 'Trống')
                    ->color(fn (DiningTable $record) => $record->openSession?->status->getColor() ?? 'gray'),
                ToggleColumn::make('is_active')
                    ->label('Đang phục vụ'),
            ])
            ->filters([
                SelectFilter::make('area_id')
                    ->label('Khu vực')
                    ->relationship('area', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('qr')
                    ->label('Mã QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->modalHeading(fn (DiningTable $record) => 'Mã QR - '.$record->displayName())
                    ->modalContent(fn (DiningTable $record) => view('admin.qr-modal', ['table' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->extraModalFooterActions(fn (DiningTable $record) => [
                        Action::make('print')
                            ->label('In mã QR')
                            ->icon(Heroicon::OutlinedPrinter)
                            ->url(route('qr.print', ['ids' => $record->id]), shouldOpenInNewTab: true),
                    ]),
                Action::make('regenerateQr')
                    ->label('Đổi mã QR')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Mã QR cũ đã in sẽ không dùng được nữa, cần in và dán lại mã mới. Dùng khi mã QR bị lộ hoặc bị chụp lại.')
                    ->action(function (DiningTable $record) {
                        $record->regenerateQrToken();

                        Notification::make()->title('Đã đổi mã QR cho '.$record->displayName())->success()->send();
                    }),
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (DiningTable $record) => $record->openSession !== null),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printQr')
                        ->label('In mã QR')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->action(fn (Collection $records, Component $livewire) => $livewire->js(
                            'window.open('.json_encode(route('qr.print', ['ids' => $records->pluck('id')->join(',')])).', "_blank")',
                        ))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
