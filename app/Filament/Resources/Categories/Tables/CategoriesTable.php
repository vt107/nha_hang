<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('menuItems'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('Ảnh')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->label('Tên danh mục')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('menu_items_count')
                    ->label('Số món')
                    ->badge(),
                ToggleColumn::make('is_active')
                    ->label('Hiển thị'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Category $record) => $record->menu_items_count > 0)
                    ->tooltip('Chỉ xóa được danh mục chưa có món'),
            ]);
    }
}
