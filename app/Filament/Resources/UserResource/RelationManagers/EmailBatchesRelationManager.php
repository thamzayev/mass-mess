<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EmailBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'emailBatches'; // Ensure relation exists in User model

     protected static ?string $navigationTitle = 'Email Batches';

    public function form(Form $form): Form
    {
        // Read-only form for viewing batch details
        return $form
            ->schema([
                Forms\Components\TextInput::make('status')->readOnly(),
                Forms\Components\TextInput::make('total_recipients')->readOnly()->numeric(),
                Forms\Components\DateTimePicker::make('created_at')->readOnly(),
                // Add more fields if needed for view
            ]);
    }

    public function table(Table $table): Table
    {
        // Reuse columns from EmailBatchResource table
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                 Tables\Columns\TextColumn::make('id')
                     ->label('Batch ID')
                     ->sortable(),
                 Tables\Columns\TextColumn::make('smtpConfiguration.name') // Show related config name
                    ->label('SMTP Config')
                    ->sortable(),
                 Tables\Columns\BadgeColumn::make('status')
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
                // Tables\Actions\CreateAction::make(), // Cannot create from here
            ])
            ->actions([
                 Tables\Actions\Action::make('view_batch')
                     ->label('View Batch')
                     ->icon('heroicon-o-eye')
                     ->url(fn ($record) => \App\Filament\Resources\EmailBatchResource::getUrl('view', ['record' => $record])),
                // Tables\Actions\DeleteAction::make(), // Use delete if you want to delete the batch itself
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
