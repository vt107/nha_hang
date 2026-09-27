<?php

namespace App\Filament\Resources\MenuItems\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->defaultGroup(Group::make('category.name')->label('Danh mục')->collapsible())
            ->columns([
                ImageColumn::make('image')
                    ->label('Ảnh')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->label('Tên món')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record) => str($record->description)->limit(60)),
                TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->label('Giá')
                    ->money('VND', locale: 'vi')
                    ->sortable(),
                ToggleColumn::make('is_available')
                    ->label('Còn món'),
                ToggleColumn::make('is_active')
                    ->label('Hiện'),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Danh mục')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_available')
                    ->label('Còn món'),
                TernaryFilter::make('is_active')
                    ->label('Hiện trên menu'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAvailable')
                        ->label('Đánh dấu còn món')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each->update(['is_available' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markSoldOut')
                        ->label('Đánh dấu hết món')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_available' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
