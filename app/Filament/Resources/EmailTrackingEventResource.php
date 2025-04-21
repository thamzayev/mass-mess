<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTrackingEventResource\Pages;
use App\Filament\Resources\EmailTrackingEventResource\RelationManagers;
use App\Models\EmailTrackingEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmailTrackingEventResource extends Resource
{
    protected static ?string $model = EmailTrackingEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('email_batch_id')
                    ->relationship('emailBatch', 'id')
                    ->required(),
                Forms\Components\TextInput::make('recipient_identifier')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\DateTimePicker::make('tracked_at')
                    ->required(),
                Forms\Components\TextInput::make('ip_address'),
                Forms\Components\Textarea::make('user_agent')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('link_url')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('emailBatch.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient_identifier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tracked_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTrackingEvents::route('/'),
            'create' => Pages\CreateEmailTrackingEvent::route('/create'),
            'view' => Pages\ViewEmailTrackingEvent::route('/{record}'),
            'edit' => Pages\EditEmailTrackingEvent::route('/{record}/edit'),
        ];
    }
}
