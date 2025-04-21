<?php

namespace App\Filament\Resources\EmailBatchResource\Pages;

use App\Filament\Resources\EmailBatchResource;
use App\Jobs\ProcessEmailBatchJob;
use App\Services\CsvProcessingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateEmailBatch extends CreateRecord
{
    protected static string $resource = EmailBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'pending';
        // $csvService = app(CsvProcessingService::class);
        // try {
        //     $data['total_recipients'] = $csvService->getTotalRows($data['csv_file_path'], 'local');
        // } catch (\Exception $e) {
        //     Log::error("Failed to count rows for batch creation: " . $e->getMessage());
        //     $data['total_recipients'] = 0;
        // }

        return $data;
    }

    protected function afterCreate(): void
    {
        $batchRecord = $this->record;

        if ($batchRecord->csv_file_path && $batchRecord->total_recipients > 0) {
            try {
                ProcessEmailBatchJob::dispatch(['email_batch_id' => $batchRecord->id]);

                $batchRecord->status = 'generating';
                $batchRecord->save();
            } catch (Throwable $e) {
                Log::error('Failed to dispatch email batch job for batch ID ' . $batchRecord->id . ': ' . $e->getMessage());
                $batchRecord->status = 'failed';
                $batchRecord->save();
                //$this->notify('danger', 'Failed to queue the email batch for processing.');
            }
        } else {
            Log::warning('Email batch created but no CSV path or recipients found. Batch ID: ' . $batchRecord->id);
            $batchRecord->status = 'failed';
            $batchRecord->save();
            //$this->notify('warning', 'Email batch created, but no recipients found in the CSV or file path missing.');
        }
    }
}
