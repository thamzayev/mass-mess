<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use App\Jobs\GenerateBatchEmailsJob;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailBatch extends EditRecord
{
    protected static string $resource = EmailBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            //$this->getGenerateEmailsAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getGenerateEmailsAction(): Action
    {
        return Action::make('generateEmails')
            ->label('Generate Emails')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Generate Emails')
            ->modalDescription('Are you sure you want to start generating emails for this batch? This process will run in the background.')
            ->action(function () {
                $batch = $this->getRecord();

                if (!$batch) {
                    Notification::make()
                        ->title('Error')
                        ->body('Batch record not found.')
                        ->danger()
                        ->send();
                    return;
                }

                if (!in_array($batch->status, ['draft', 'failed'])) {
                     Notification::make()
                        ->title('Cannot Generate')
                        ->body('Emails can only be generated for batches in draft or failed status.')
                        ->warning()
                        ->send();
                    return;
                }

                $batch->update(['status' => 'generating', 'generated_count' => 0, 'failed_count' => 0]); // Reset counts
                GenerateBatchEmailsJob::dispatch($batch);

                Notification::make()
                    ->title('Batch Generation Started')
                    ->body('The batch generation process has started for batch ID: ' . $batch->id)
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->getRecord() && in_array($this->getRecord()->status, ['draft', 'failed']));
    }
}
