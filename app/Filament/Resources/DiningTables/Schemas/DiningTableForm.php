<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiningTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('area_id')
                            ->label('Khu vực')
                            ->relationship('area', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->preload()
                            ->required(),
                        TextInput::make('code')
                            ->label('Mã bàn')
                            ->placeholder('A01')
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (string $state) => strtoupper($state)),
                        TextInput::make('name')
                            ->label('Tên hiển thị')
                            ->placeholder('Để trống sẽ hiện "Bàn A01"')
                            ->maxLength(100),
                        TextInput::make('capacity')
                            ->label('Số ghế')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(4)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Thứ tự')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Đang phục vụ')
                            ->helperText('Tắt thì khách quét QR sẽ không gọi món được.')
                            ->default(true),
                    ]),
            ]);
    }
}
