<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use App\Jobs\GenerateBatchEmailsJob;
use App\Models\EmailBatch;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Actions\Action as NotificationAction;
use Exception;

class CreateEmailBatch extends CreateRecord
{
    protected static string $resource = EmailBatchResource::class;

    public string $creationType = 'draft';

    public function mount(): void
    {
        $cloneFromId = request()->query('clone_from');

        if ($cloneFromId) {
            $this->handleCloning((int)$cloneFromId);
            // handleCloning will redirect if successful or on error,
            // so parent::mount() won't be called in the cloning case.
        } else {
            parent::mount();
        }
    }


    protected function handleCloning(int $cloneFromId): void
    {
        $sourceBatch = EmailBatch::with('personalizedAttachments')->find($cloneFromId);

        if (!$sourceBatch) {
            Notification::make()
                ->title('Clone Error')
                ->body("EmailBatch with ID {$cloneFromId} not found for cloning.")
                ->warning()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        if ($sourceBatch->user_id !== Auth::id()) {
            Notification::make()
                ->title('Permission Denied')
                ->body("You do not have permission to clone this email batch.")
                ->danger()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        try {
            DB::beginTransaction();


            $newBatch = $sourceBatch->replicate([
                'id', 'created_at', 'updated_at', 'status',
                'generated_count', 'sent_count', 'failed_count'
            ]);

            $newBatch->name = $sourceBatch->name . ' (Clone)';
            $newBatch->user_id = Auth::id();
            $newBatch->status = 'draft';
            $newBatch->generated_count = 0;
            $newBatch->sent_count = 0;
            $newBatch->failed_count = 0;

            $newBatch->save();
            if ($sourceBatch->relationLoaded('personalizedAttachments')) {
                foreach ($sourceBatch->personalizedAttachments as $attachment) {
                    $newAttachment = $attachment->replicate(['id', 'email_batch_id', 'created_at', 'updated_at']);
                    $newAttachment->email_batch_id = $newBatch->id;
                    $newAttachment->save();
                }
            }

            DB::commit();

            Notification::make()
                ->title('Batch Cloned Successfully')
                ->body("Email Batch ID {$sourceBatch->id} has been cloned as new draft (ID {$newBatch->id}).")
                ->success()
                ->actions([
                    NotificationAction::make('edit_cloned')
                        ->label('Edit Cloned Batch (ID ' . $newBatch->id . ')')
                        ->url($this->getResource()::getUrl('edit', ['record' => $newBatch]))
                        ->button()
                ])
                ->persistent()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error cloning EmailBatch ID {$cloneFromId}: " . $e->getMessage(), ['exception' => $e]);
            Notification::make()
                ->title('Cloning Failed')
                ->body('An unexpected error occurred while cloning the email batch: ' . $e->getMessage())
                ->danger()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['user_id'] = Auth::id();
        $data['status'] = $this->creationType === 'generate' ? 'generating' : 'draft';

        $batch = static::getModel()::create($data);

        return $batch;
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('saveDraft')
                ->label('Save Draft')
                ->action(function () {
                    $this->creationType = 'draft';
                    $this->create();
                }),

            Actions\Action::make('saveAndGenerate')
                ->label('Save and Generate Emails')
                ->action(function () {
                    $this->creationType = 'generate';
                    $this->create();
                }),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (!$record instanceof EmailBatch) {
            return;
        }

        if ($this->creationType === 'draft') {
            Notification::make()
                ->title('Draft Saved')
                ->body("The email batch (ID: {$record->id}) has been saved as a draft.")
                ->success()
                ->send();
        } elseif ($this->creationType === 'generate' && $record->status === 'generating') {
            GenerateBatchEmailsJob::dispatch($record);
            Notification::make()
                ->title('Batch Generation Started')
                ->body('The batch generation process has started for batch ID: ' . $record->id)
                ->success()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
 }
