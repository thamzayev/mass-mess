<?php

namespace App\Filament\Resources\SmtpConfigurationResource\Pages;

use App\Filament\Resources\SmtpConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSmtpConfigurations extends ListRecords
{
    protected static string $resource = SmtpConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
