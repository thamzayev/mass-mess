<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailBatch extends EditRecord
{
    protected static string $resource = EmailBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
