<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Thông tin món')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên món')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Đường dẫn')
                            ->required()
                            ->maxLength(180)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->label('Danh mục')
                            ->relationship('category', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('price')
                            ->label('Giá bán')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->step(1000)
                            ->suffix('₫'),
                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('Ảnh món')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('menu')
                            ->maxSize(4096)
                            ->columnSpanFull(),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Hiển thị')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Hiện trên menu')
                                    ->default(true),
                                Toggle::make('is_available')
                                    ->label('Còn món')
                                    ->helperText('Tắt khi hết món trong ngày; bếp cũng bật / tắt được.')
                                    ->default(true),
                                Toggle::make('is_featured')
                                    ->label('Món nổi bật'),
                                TextInput::make('sort_order')
                                    ->label('Thứ tự')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
