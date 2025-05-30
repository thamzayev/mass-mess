<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmtpConfigurationResource\Pages;
use App\Models\SmtpConfiguration;
use App\Services\RateLimitService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth; // Needed for scoping query

class SmtpConfigurationResource extends Resource
{
    protected static ?string $model = SmtpConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack'; // Choose an icon

    protected static ?string $navigationGroup = 'Settings'; // Group in sidebar

    // Scope queries to the logged-in user
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function form(Form $form): Form
    {
        // Inject RateLimitService to get provider suggestions
        $rateLimitService = app(RateLimitService::class);
        $providerLimits = $rateLimitService->getSuggestedRateLimits();
        $providerOptions = array_keys($providerLimits); // Get just the names for select options

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2) // Grid for better layout
                    ->schema([
                        Forms\Components\TextInput::make('host')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('port')
                            ->required()
                            ->numeric(),
                    ]),
                 Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            // ->revealable() // Consider adding revealable option
                            ->maxLength(255),
                            // ->dehydrated(fn ($state) => filled($state)) // Only save if filled
                            // ->required(fn (string $context): bool => $context === 'create'), // Require on create?
                    ]),
                 Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                            ])
                            ->placeholder('None'), // Allow null
                         Forms\Components\Select::make('provider_suggestion') // Helper field
                            ->label('Common Provider')
                            ->options(array_combine($providerOptions, $providerOptions)) // Use provider names for options
                            ->placeholder('Select a provider for rate limit info')
                            ->live() // Update the form when changed
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) use ($providerLimits) {
                                // You might pre-fill common settings here if needed
                                // e.g., if ($state === 'Gmail') { $set('host', 'smtp.gmail.com'); ... }
                            })
                            ->dehydrated(false), // Don't save this field
                    ]),
                 // Display rate limit info based on provider selection
                 Forms\Components\Placeholder::make('rate_limit_info')
                     ->label('Suggested Rate Limit')
                     ->content(function (Forms\Get $get) use ($providerLimits): string {
                        $provider = $get('provider_suggestion');
                        return $provider && isset($providerLimits[$provider])
                            ? $providerLimits[$provider]
                            : 'Select a common provider above to see suggested limits.';
                     })
                     ->visible(fn (Forms\Get $get): bool => (bool)$get('provider_suggestion')), // Only show if provider is selected
                  Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('from_address')
                            ->label('Default From Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('from_name')
                            ->label('Default From Name')
                            ->maxLength(255),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('host')
                    ->searchable(),
                Tables\Columns\TextColumn::make('port'),
                Tables\Columns\TextColumn::make('from_address')
                     ->label('From Address')
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
                // Add filters if needed
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //RelationManagers\EmailBatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmtpConfigurations::route('/'),
            'create' => Pages\CreateSmtpConfiguration::route('/create'),
            'edit' => Pages\EditSmtpConfiguration::route('/{record}/edit'),
        ];
    }
}
