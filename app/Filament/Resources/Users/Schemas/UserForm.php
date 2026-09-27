<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Họ tên')
                            ->required()
                            ->maxLength(100),
                        Select::make('role')
                            ->label('Vai trò')
                            ->options(fn () => collect(self::assignableRoles())
                                ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()]))
                            ->required()
                            ->native(false),
                        TextInput::make('email')
                            ->label('Email đăng nhập')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(fn (string $operation) => $operation === 'create' ? 'Mật khẩu' : 'Mật khẩu mới')
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Để trống nếu không đổi.' : null)
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state)),
                        Toggle::make('is_active')
                            ->label('Đang làm việc')
                            ->helperText('Tắt để khóa đăng nhập.')
                            ->default(true)
                            ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                    ]),
            ]);
    }

    /**
     * @return list<UserRole>
     */
    private static function assignableRoles(): array
    {
        return auth()->user()?->role === UserRole::Admin ? UserRole::cases() : UserPolicy::managedByManager();
    }
}
