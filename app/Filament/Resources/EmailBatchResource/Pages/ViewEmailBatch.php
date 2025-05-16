<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use App\Models\EmailBatch;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailBatch extends ViewRecord
{
    protected static string $resource = EmailBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->disabled(function (EmailBatch $record): bool {
                    return $record->status === 'sent';
                })
                ->tooltip(function (EmailBatch $record): ?string {
                    return $record->status === 'sent' ? 'Cannot edit a batch that has already been sent.' : null;
                }),
        ];
    }
}
