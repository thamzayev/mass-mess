<?php

namespace App\Filament\Resources\SmtpConfigurationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmailBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'emailBatches';

    // Optional: Define a navigation title if needed, usually inferred
    // protected static ?string $navigationTitle = 'Related Email Batches';

    public function form(Form $form): Form
    {
        // Usually, you don't edit batches from the SMTP config relation manager
        // This form is primarily for viewing details if a modal view is used
        return $form
            ->schema([
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->readOnly(),
                Forms\Components\TextInput::make('total_recipients')
                     ->numeric()
                     ->readOnly(),
                Forms\Components\DateTimePicker::make('created_at')
                     ->readOnly(),
            ]);
    }

    public function table(Table $table): Table
    {
        // Use relevant columns from EmailBatchResource's table
        return $table
            ->recordTitleAttribute('status') // Or 'id', 'created_at'
            ->columns([
                 Tables\Columns\TextColumn::make('id')
                     ->label('Batch ID')
                     ->sortable(),
                 Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'warning' => fn ($state): bool => in_array($state, ['generating', 'sending']),
                        'success' => 'sent',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('sent_count')
                    ->numeric(),
                Tables\Columns\TextColumn::make('failed_count')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // Add filters if needed, e.g., by status
                 Tables\Filters\SelectFilter::make('status')
                     ->options([
                        'pending' => 'Pending',
                        'generating' => 'Generating',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                     ]),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(), // Don't allow creating batches from here
            ])
            ->actions([
                 // Link to view the actual batch resource
                 Tables\Actions\Action::make('view_batch')
                     ->label('View Batch')
                     ->icon('heroicon-o-eye')
                     ->url(fn ($record) => \App\Filament\Resources\EmailBatchResource::getUrl('view', ['record' => $record])),
                 // Tables\Actions\EditAction::make(), // Don't edit from here
                 // Tables\Actions\DeleteAction::make(), // Deleting SMTP config should cascade or restrict
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
             ->defaultSort('created_at', 'desc');
    }
}
