<?php

namespace App\Filament\Resources\EmailTrackingEventResource\Pages;

use App\Filament\Resources\EmailTrackingEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailTrackingEvent extends ViewRecord
{
    protected static string $resource = EmailTrackingEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
