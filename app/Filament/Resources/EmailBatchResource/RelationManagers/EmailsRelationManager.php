<?php

namespace App\Filament\Resources\EmailBatchResource\RelationManagers;

use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailsRelationManager extends RelationManager
{
    protected static string $relationship = 'Emails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email_batch_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('to_address')
                    ->label('To Address')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('generated_attachments')
                    ->label('Attachments')
                    ->html() // Allow rendering HTML (like <br>)
                    ->tooltip('Files generated for this email.')
                    ->getStateUsing(function (Email $record): string {
                        $batchId = $this->ownerRecord->id; // Get the parent EmailBatch ID
                        $emailId = $record->id; // Get the current Email record ID

                        // --- WARNING: Potential Performance Issue ---
                        // This approach fetches all email IDs for the batch and finds the index.
                        // For large batches, this query runs for *each* row displayed.
                        // A more performant solution might require storing the index in the database
                        // or finding a way to calculate indices in bulk for the current page.
                        $allBatchEmailIds = Email::where('email_batch_id', $batchId)
                            ->orderBy('id') // Assuming index is based on ID order
                            ->pluck('id');

                        $index = $allBatchEmailIds->search($emailId); // 0-based index

                        if ($index === false) {
                            // This should ideally not happen if the record belongs to the batch
                            Log::warning("Email record ID {$emailId} not found in batch {$batchId}'s sorted list.");
                            return 'Error determining index.';
                        }

                        $rowNumber = $index + 1; // Convert to 1-based index

                        $folderPath = "personalized-attachments/batch_{$batchId}/row_{$rowNumber}";

                        // Use the 'private' disk configured in config/filesystems.php
                        $disk = Storage::disk('private');

                        if (!$disk->exists($folderPath)) {
                             return 'No attachments folder found.';
                        }

                        $files = $disk->files($folderPath);

                        if (empty($files)) {
                            return 'No attachments.';
                        }
                        $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>';
                        $icon = '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="icon" style="width:24px;height:24px;display:inline-block;vertical-align:middle;" />';

                        $output = [];
                        foreach ($files as $file) {
                            $filename = basename($file);
                            $url = route('attachments.download', ['path' => $file]);
                            $output[] = "<a href=\"{$url}\" target=\"_blank\" title=\"{$filename}\">{$icon}</a>";
                        }

                        return implode(' ', $output);
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->actions([
            ])
            ->bulkActions([
            ]);
    }
}
