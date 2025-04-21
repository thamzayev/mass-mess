<?php

namespace App\Filament\Resources\EmailBatchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmailTrackingEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'trackingEvents';

    protected static ?string $navigationTitle = 'Tracking Events';

    public function form(Form $form): Form
    {
        // Usually read-only view in relation manager
        return $form
            ->schema([
                 Forms\Components\TextInput::make('recipient_identifier')->readOnly(),
                 Forms\Components\TextInput::make('type')->readOnly(),
                 Forms\Components\DateTimePicker::make('tracked_at')->readOnly(),
                 Forms\Components\TextInput::make('ip_address')->readOnly(),
                 Forms\Components\Textarea::make('user_agent')->readOnly()->columnSpanFull(),
                 Forms\Components\Textarea::make('link_url')->readOnly()->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
         // Reuse columns from EmailTrackingEventResource table for consistency
         return $table
             ->recordTitleAttribute('recipient_identifier') // Or another suitable attribute
             ->columns([
                Tables\Columns\TextColumn::make('recipient_identifier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'open',
                        'info' => 'click',
                    ]),
                Tables\Columns\TextColumn::make('link_url')
                    ->label('Clicked URL')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record?->link_url)
                    ->visible(fn ($record) => $record?->type === 'click'),
                Tables\Columns\TextColumn::make('tracked_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->toggleable(isToggledHiddenByDefault: true),
             ])
             ->filters([
                 Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'open' => 'Open',
                        'click' => 'Click',
                    ]),
             ])
             ->headerActions([
                 // Tables\Actions\CreateAction::make(), // Don't allow creating from here
             ])
             ->actions([
                  Tables\Actions\ViewAction::make(),
                 // Tables\Actions\EditAction::make(), // Don't allow editing
                 // Tables\Actions\DeleteAction::make(), // Maybe allow deleting?
             ])
             ->bulkActions([
                 // Tables\Actions\BulkActionGroup::make([
                 //     Tables\Actions\DeleteBulkAction::make(),
                 // ]),
             ])
             ->defaultSort('tracked_at', 'desc');
    }
}
