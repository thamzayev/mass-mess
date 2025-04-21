<?php

namespace App\Filament\Resources\EmailBatchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage; // For download action

class PersonalizedAttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'personalizedAttachments';

    protected static ?string $navigationTitle = 'Personalized Attachments';


    public function form(Form $form): Form
    {
         // Read-only form for viewing details
        return $form
            ->schema([
                Forms\Components\TextInput::make('recipient_identifier')
                    ->readOnly(),
                Forms\Components\TextInput::make('original_name')
                    ->readOnly(),
                Forms\Components\TextInput::make('file_path')
                    ->readOnly(),
                 Forms\Components\DateTimePicker::make('created_at')
                    ->readOnly(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_identifier')
            ->columns([
                Tables\Columns\TextColumn::make('recipient_identifier')
                     ->searchable()
                     ->sortable(),
                Tables\Columns\TextColumn::make('original_name')
                     ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(), // Generated automatically, cannot create manually
            ])
            ->actions([
                 Tables\Actions\ViewAction::make(),
                 // Add download action
                 Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        try {
                            // Assuming 'private' disk where personalized attachments are stored
                            return Storage::disk('private')->download($record->file_path, $record->original_name ?? basename($record->file_path));
                        } catch (\Exception $e) {
                            // Handle file not found or other storage errors
                            \Filament\Notifications\Notification::make()
                                ->title('Download Failed')
                                ->body('The attachment file could not be found or downloaded.')
                                ->danger()
                                ->send();
                            return null; // Prevent further action
                        }
                    }),
                // Tables\Actions\DeleteAction::make(), // Maybe allow deleting individual attachments?
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
             ->defaultSort('created_at', 'asc'); // Or sort by recipient_identifier
    }
}
