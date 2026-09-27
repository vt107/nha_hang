<?php

namespace App\Filament\Resources\OptionGroups;

use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\ListOptionGroups;
use App\Models\OptionGroup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Nhóm tùy chọn (Size, Topping, Độ cay...) dùng chung cho nhiều món.
 */
class OptionGroupResource extends Resource
{
    protected static ?string $model = OptionGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Thực đơn';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'nhóm tùy chọn';

    protected static ?string $pluralModelLabel = 'Size / topping';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Nhóm tùy chọn')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên hiển thị cho khách')
                            ->placeholder('Size, Topping, Độ cay...')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('internal_name')
                            ->label('Tên phân biệt trong admin')
                            ->placeholder('Size - đồ uống')
                            ->maxLength(100),
                        TextInput::make('min_select')
                            ->label('Chọn tối thiểu')
                            ->helperText('0 = không bắt buộc')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(20)
                            ->default(0)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('max_select')
                            ->label('Chọn tối đa')
                            ->helperText('1 = chỉ chọn một (vd Size)')
                            ->integer()
                            ->minValue(fn (Get $get) => max(1, (int) $get('min_select')))
                            ->maxValue(20)
                            ->default(1)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Thứ tự')
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Đang dùng')
                            ->default(true),
                        Select::make('menuItems')
                            ->label('Áp dụng cho món')
                            ->relationship('menuItems', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
                Section::make('Lựa chọn')
                    ->columnSpan(2)
                    ->schema([
                        Repeater::make('options')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->columns(4)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Thêm lựa chọn')
                            ->itemLabel(fn (array $state) => $state['name'] ?? null)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Tên')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(2),
                                TextInput::make('price_delta')
                                    ->label('Cộng thêm')
                                    ->integer()
                                    ->minValue(0)
                                    ->step(1000)
                                    ->default(0)
                                    ->suffix('₫')
                                    ->required()
                                    ->columnSpan(2),
                                Toggle::make('is_default')
                                    ->label('Chọn sẵn')
                                    ->columnSpan(2),
                                Toggle::make('is_available')
                                    ->label('Còn hàng')
                                    ->default(true)
                                    ->columnSpan(2),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['options', 'menuItems']))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nhóm')
                    ->weight('medium')
                    ->description(fn (OptionGroup $record) => $record->internal_name)
                    ->searchable(['name', 'internal_name']),
                TextColumn::make('rule')
                    ->label('Quy tắc')
                    ->state(fn (OptionGroup $record) => $record->selectionHint())
                    ->badge()
                    ->color(fn (OptionGroup $record) => $record->isRequired() ? 'warning' : 'gray'),
                TextColumn::make('options_count')
                    ->label('Số lựa chọn'),
                TextColumn::make('menu_items_count')
                    ->label('Số món áp dụng'),
                ToggleColumn::make('is_active')
                    ->label('Đang dùng'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionGroups::route('/'),
            'create' => CreateOptionGroup::route('/create'),
            'edit' => EditOptionGroup::route('/{record}/edit'),
        ];
    }
}
