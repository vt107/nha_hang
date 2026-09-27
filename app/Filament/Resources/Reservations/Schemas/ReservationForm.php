<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Khách hàng')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Họ tên')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('customer_phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                        TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),
                        TextInput::make('party_size')
                            ->label('Số người')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                        DateTimePicker::make('reserved_at')
                            ->label('Thời gian đến')
                            ->seconds(false)
                            ->minutesStep(15)
                            ->native(false)
                            ->displayFormat('H:i d/m/Y')
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label('Thời lượng giữ bàn')
                            ->integer()
                            ->minValue(30)
                            ->default(120)
                            ->suffix('phút')
                            ->required(),
                        Textarea::make('note')
                            ->label('Ghi chú của khách')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Xử lý')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('code')
                            ->label('Mã đặt bàn')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(ReservationStatus::class)
                            ->default(ReservationStatus::Confirmed)
                            ->required()
                            ->native(false),
                        Select::make('dining_table_id')
                            ->label('Bàn')
                            ->options(fn () => DiningTable::query()
                                ->active()
                                ->with('area')
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (DiningTable $table) => [
                                    $table->id => "{$table->code} · {$table->area->name} · {$table->capacity} ghế",
                                ]))
                            ->searchable()
                            ->placeholder('Chưa gán bàn'),
                        Select::make('source')
                            ->label('Nguồn')
                            ->options(ReservationSource::class)
                            ->default(ReservationSource::Phone)
                            ->required()
                            ->native(false),
                        Textarea::make('internal_note')
                            ->label('Ghi chú nội bộ')
                            ->rows(3),
                    ]),
            ]);
    }
}
