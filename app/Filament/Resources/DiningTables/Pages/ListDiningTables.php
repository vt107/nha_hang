<?php

namespace App\Filament\Resources\DiningTables\Pages;

use App\Filament\Resources\DiningTables\DiningTableResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDiningTables extends ListRecords
{
    protected static string $resource = DiningTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printAll')
                ->label('In QR tất cả bàn')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(route('qr.print'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
