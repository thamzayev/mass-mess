<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailBatch extends ViewRecord
{
    protected static string $resource = EmailBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
