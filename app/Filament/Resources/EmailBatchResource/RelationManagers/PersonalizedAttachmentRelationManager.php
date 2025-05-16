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
                Forms\Components\RichEditor::make('header')
                    ->readOnly(),
                Forms\Components\RichEditor::make('template')
                    ->readOnly(),
                Forms\Components\RichEditor::make('footer')
                    ->readOnly(),
                Forms\Components\TextInput::make('filename')
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
                Tables\Columns\TextColumn::make('filename')
                     ->searchable()
                     ->sortable(),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
             ->defaultSort('created_at', 'asc'); // Or sort by recipient_identifier
    }
}
