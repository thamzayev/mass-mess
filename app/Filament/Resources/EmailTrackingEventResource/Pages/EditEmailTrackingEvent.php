<?php

namespace App\Filament\Resources\EmailTrackingEventResource\Pages;

use App\Filament\Resources\EmailTrackingEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailTrackingEvent extends EditRecord
{
    protected static string $resource = EmailTrackingEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
