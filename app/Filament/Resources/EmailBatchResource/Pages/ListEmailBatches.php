<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailBatches extends ListRecords
{
    protected static string $resource = EmailBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
