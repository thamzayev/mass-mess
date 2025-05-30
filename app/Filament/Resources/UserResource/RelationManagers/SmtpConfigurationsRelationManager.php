<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SmtpConfigurationsRelationManager extends RelationManager
{
    protected static string $relationship = 'smtpConfigurations'; // Ensure relation exists in User model

    protected static ?string $navigationTitle = 'SMTP Configurations';

    public function form(Form $form): Form
    {
        // Use form from SmtpConfigurationResource for consistency (read-only view)
        return \App\Filament\Resources\SmtpConfigurationResource::form($form)
            ->disabled(); // Make fields read-only
    }

    public function table(Table $table): Table
    {
         // Reuse columns from SmtpConfigurationResource table
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('host'),
                Tables\Columns\TextColumn::make('from_address')
                     ->label('From Address'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(), // Don't create from here
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('edit_config')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record) => \App\Filament\Resources\SmtpConfigurationResource::getUrl('edit', ['record' => $record])),
                // Tables\Actions\DetachAction::make(), // Use detach if you only want to unlink
                // Tables\Actions\DeleteAction::make(), // Use delete if you want to delete the config itself
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
