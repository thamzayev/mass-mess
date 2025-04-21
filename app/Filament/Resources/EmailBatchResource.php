<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailBatchResource\Pages;
use App\Filament\Resources\EmailBatchResource\RelationManagers;
use App\Jobs\ProcessEmailBatchJob;
use App\Models\EmailBatch;
use App\Models\SmtpConfiguration;
use App\Services\CsvProcessingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Throwable;

class EmailBatchResource extends Resource
{
    protected static ?string $model = EmailBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    // Scope queries to the logged-in user
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Upload CSV')
                        ->schema([
                            Forms\Components\FileUpload::make('csv_file_path')
                                ->label('Upload Recipient CSV File')
                                ->required()
                                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                                ->directory('csv-uploads')
                                ->visibility('private')
                                ->storeFiles(true)
                                ->reactive()
                                ->afterStateUpdated(function (?UploadedFile $state, Forms\Set $set, Forms\Get $get) {
                                    if ($state) {
                                        try {
                                            $path = $state->store('temp/csv-uploads', ['disk' => 'local']);
                                            $csvService = app(CsvProcessingService::class);
                                            $headers = $csvService->getHeaders($path);
                                            $set('csv_headers_available', true);
                                            $set('data_headers', $headers);

                                            $numberOfRows = $csvService->getTotalRows($path);
                                            $set('total_recipients', $numberOfRows);

                                            $records = $csvService->getRecords($path);
                                            $set('data_rows', json_encode($records)); // Store records for later use


                                        } catch (\Exception $e) {
                                            Log::error("Error processing CSV file: " . $e->getMessage());
                                            $set('csv_headers_available', false); // Indicate failure
                                        }
                                    } else {
                                        $set('csv_headers_available', false);
                                    }
                                })
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('data_headers')
                                ->label('Available CSV Headers')
                                ->placeholder('Headers found in the uploaded CSV file are below.')
                                ->disabled()
                                ->dehydrated(true)
                                ->visible(fn (Forms\Get $get) => $get('csv_headers_available'))
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('total_recipients')
                                ->label('Total Recipients')
                                ->disabled()
                                ->dehydrated(true)
                                ->visible(fn (Forms\Get $get) => $get('csv_headers_available'))
                                ->columnSpanFull(),
                        ]),
                    Wizard\Step::make('Email Content')
                        ->schema([
                            Forms\Components\TagsInput::make('data_headers')
                                ->label('Available CSV Headers')
                                ->placeholder('Headers found in the uploaded CSV file are below.')
                                ->visible(fn (Forms\Get $get) => $get('csv_headers_available'))
                                ->disabled()
                                ->dehydrated(true)
                                ->tagPrefix('{{ $')
                                ->tagSuffix(' }}')
                                ->hint('Use these headers in your email templates.')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('email_title_template')
                                ->label('Email Subject Template')
                                ->hint('Use {{ $header_name }} for dynamic content.') // Adjust hint based on syntax
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('email_body_template')
                                ->label('Email Body Template')
                                ->hint('Use {{ $header_name }} for dynamic content.') // Adjust hint
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\Repeater::make('attachment_paths')
                                 ->label('Static Attachments')
                                 ->schema([
                                     Forms\Components\FileUpload::make('path')
                                         ->directory('static-attachments')
                                         ->visibility('private')
                                         ->required(),
                                 ])
                                 ->grid(1) // Layout repeater items
                                 ->columnSpanFull(),
                            Forms\Components\Toggle::make('has_personalized_attachments')
                                ->label('Enable Personalized Attachment?')
                                ->reactive()
                                ->columnSpanFull(),
                            Forms\Components\Repeater::make('personalized_attachments')
                                ->label('Personalized Attachments')
                                ->schema([
                                    Forms\Components\RichEditor::make('template')
                                        ->label('Attachment Template (PDF)')
                                        ->hint('Use {{ $header_name }} for dynamic content.')
                                        ->required(),
                                ])
                                ->required(fn (Forms\Get $get): bool => $get('has_personalized_attachments')) // Require if toggle is on
                                ->visible(fn (Forms\Get $get): bool => $get('has_personalized_attachments')) // Show if toggle is on
                                ->columnSpanFull(),
                            Forms\Components\Toggle::make('tracking_enabled')
                                 ->label('Enable Email Open/Click Tracking?')
                                 ->default(true)
                                 ->columnSpanFull(),
                        ]),
                    Wizard\Step::make('Configuration & Sending')
                         ->schema([
                            Forms\Components\Select::make('smtp_configuration_id')
                                ->label('Select SMTP Configuration')
                                ->relationship( // Use relationship for better performance
                                    name: 'smtpConfiguration',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => $query->where('user_id', Auth::id()) // Filter by user
                                 )
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                ])->columnSpanFull() // Make wizard span full width
                  ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md filament-button-color-primary">Create & Start Sending</button>')), // Customize button text if needed
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                     ->sortable(),
                Tables\Columns\TextColumn::make('smtpConfiguration.name') // Show related config name
                    ->label('SMTP Config')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'warning' => fn ($state): bool => in_array($state, ['generating', 'sending']),
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('sent_count')
                    ->numeric(),
                Tables\Columns\TextColumn::make('failed_count')
                    ->numeric(),
                Tables\Columns\IconColumn::make('tracking_enabled')
                    ->label('Tracking')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false), // Show by default
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
                 Tables\Filters\SelectFilter::make('smtp_configuration_id')
                     ->label('SMTP Config')
                     ->relationship('smtpConfiguration', 'name', fn (Builder $query) => $query->where('user_id', Auth::id())),

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Add a custom Preview Action here later
                // Tables\Actions\Action::make('preview') ...
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Add bulk actions like retry failed maybe?
                ]),
            ])
             ->defaultSort('created_at', 'desc'); // Sort by newest first
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmailTrackingEventsRelationManager::class, // Show tracking events on view page
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailBatches::route('/'),
            'create' => Pages\CreateEmailBatch::route('/create'),
            'view' => Pages\ViewEmailBatch::route('/{record}'),
            // 'edit' => Pages\EditEmailBatch::route('/{record}/edit'), // Usually don't edit batches once created
        ];
    }
}
