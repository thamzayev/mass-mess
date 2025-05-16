<?php

namespace App\Filament\Resources;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Filament\Resources\EmailBatchResource\Pages;
use App\Filament\Resources\EmailBatchResource\RelationManagers;
use App\Jobs\GenerateBatchEmailsJob;
use App\Models\EmailBatch;
use App\Services\CsvProcessingService;
use Filament\Forms;
use App\Jobs\SendGeneratedEmailsJob;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

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
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Section::make('Upload CSV File')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Batch Name')
                                ->placeholder('Enter a name for this batch')
                                ->required()
                                ->columnSpanFull(),
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
                                            $path = $state->store('temp/csv-uploads', ['disk' => 'private']);
                                            $csvService = app(CsvProcessingService::class);
                                            $headers = $csvService->getHeaders($path);
                                            $set('csv_headers_available', true);
                                            $set('data_headers', $headers);

                                            $numberOfRows = $csvService->getTotalRows($path);
                                            $set('total_recipients', $numberOfRows);

                                            $records = $csvService->getRecords($path);
                                            $set('csv_preview_data', array_slice($records, 0, 5)); // Preview first 5 rows
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
                            Forms\Components\TextInput::make('total_recipients')
                                ->label('Total Recipients')
                                ->disabled()
                                ->dehydrated()
                                ->visible(fn(Forms\Get $get) => $get('csv_headers_available'))
                                ->columnSpanFull(),
                            Forms\Components\Hidden::make('data_rows')
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\Placeholder::make('csv_preview')
                                ->label('Data Preview (First 5 Rows)')
                                ->content(function (Forms\Get $get): HtmlString {
                                    $headers = $get('data_headers');
                                    $previewData = $get('csv_preview_data');

                                    if (empty($headers) || empty($previewData)) {
                                        return new HtmlString('<p>Upload a CSV file to see a preview.</p>');
                                    }

                                    // Build HTML Table
                                    $tableHtml = '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">';
                                    $tableHtml .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">';
                                    $tableHtml .= '<thead class="bg-gray-50 dark:bg-gray-800">';
                                    $tableHtml .= '<tr>';
                                    foreach ($headers as $header) {
                                        $tableHtml .= '<th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">' . htmlspecialchars($header) . '</th>';
                                    }
                                    $tableHtml .= '</tr>';
                                    $tableHtml .= '</thead>';
                                    $tableHtml .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';
                                    foreach ($previewData as $row) {
                                        $tableHtml .= '<tr>';
                                        // Ensure row iteration matches header order
                                        foreach ($headers as $headerKey) {
                                            $value = $row[$headerKey] ?? ''; // Get value by header key
                                            $tableHtml .= '<td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-200">' . htmlspecialchars($value) . '</td>';
                                        }
                                        $tableHtml .= '</tr>';
                                    }
                                    $tableHtml .= '</tbody>';
                                    $tableHtml .= '</table>';
                                    $tableHtml .= '</div>';


                                    return new HtmlString($tableHtml);
                                })
                                ->visible(fn(Forms\Get $get) => $get('csv_headers_available'))
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Section::make('Email Configuration')
                        ->description('Configure the email settings and content.')
                        ->schema([
                            Forms\Components\Section::make()
                                ->schema([
                                    Forms\Components\Section::make('Email Content')
                                        ->description('Customize the email content and attachments.')
                                        ->schema([
                                            Forms\Components\TextInput::make('email_to')
                                                ->label('Recipient Email Address')
                                                ->placeholder('Enter the email address to send to.')
                                                ->required()
                                                ->columnSpanFull(),
                                            Forms\Components\TextInput::make('email_cc')
                                                ->label('CC Email Address')
                                                ->placeholder('Enter CC email addresses, separated by commas.')
                                                ->columnSpanFull(),
                                            Forms\Components\TextInput::make('email_bcc')
                                                ->label('BCC Email Address')
                                                ->placeholder('Enter BCC email addresses, separated by commas.')
                                                ->columnSpanFull(),
                                            Forms\Components\TextInput::make('email_subject')
                                                ->label('Email Subject')
                                                ->hint('Use [[ column_name ]] for dynamic content.') // Adjust hint based on syntax
                                                ->required()
                                                ->columnSpanFull(),
                                            TinyEditor::make('email_body')
                                                ->setRelativeUrls(false)
                                                ->setConvertUrls(false)
                                                ->label('Email Body')
                                                ->hint('Use [[ column_name ]] for dynamic content.') // Adjust hint
                                                ->required()
                                                ->columnSpanFull(),
                                        ])
                                        ->extraAttributes([
                                            'style' => 'background-color:#e9eaeb',
                                        ]),
                                    Forms\Components\Section::make('Attachments')
                                        ->description('Add static or personalized attachments.')
                                        ->schema([
                                            Forms\Components\FileUpload::make('attachment_paths')
                                                ->label('Static Attachments')
                                                ->multiple()
                                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                                ->preserveFilenames()
                                                ->storeFiles()
                                                ->disk('private')
                                                ->directory('static-attachments')
                                                ->visibility('private')
                                                ->columnSpanFull(),
                                            Forms\Components\Repeater::make('personalizedAttachments')
                                                ->relationship('personalizedAttachments')
                                                ->label('Personalized Attachments')
                                                ->schema([
                                                    Forms\Components\TextInput::make('filename')
                                                        ->label('Attachment Filename')
                                                        ->placeholder('Enter the anticipted filename for the attachment.')
                                                        ->hint('Use [[ column_name ]] for dynamic content.')
                                                        ->required(),
                                                    TinyEditor::make('header')
                                                        ->label('Attachment Header')
                                                        ->hint('Use [[ column_name ]] for dynamic content.')
                                                        ->setRelativeUrls(false)
                                                        ->setConvertUrls(false),
                                                    TinyEditor::make('template')
                                                        ->label('Attachment Template (PDF)')
                                                        ->hint('Use [[ column_name ]] for dynamic content.')
                                                        ->required()
                                                        ->setRelativeUrls(false)
                                                        ->setConvertUrls(false),
                                                    TinyEditor::make('footer')
                                                        ->label('Attachment Footer')
                                                        ->hint('Use [[ column_name ]], {{PAGE}} and {{PAGES}} for dynamic content.')
                                                        ->setRelativeUrls(false)
                                                        ->setConvertUrls(false),

                                                ])
                                                ->required(fn(Forms\Get $get): bool => $get('has_personalized_attachments')) // Require if toggle is on
                                                ->visible(fn(Forms\Get $get): bool => $get('has_personalized_attachments')) // Show if toggle is on
                                                ->collapsible()
                                                ->cloneable()
                                                ->columnSpanFull(),
                                        ])
                                        ->extraAttributes([
                                            'style' => 'background-color:#e9eaeb',
                                        ]),
                                ])->columnSpan(3),



                            Forms\Components\Section::make('Additional Settings')
                                ->description('Configure additional settings for the email batch.')
                                ->schema([
                                    Forms\Components\TagsInput::make('data_headers')
                                        ->label('Available CSV Headers')
                                        ->placeholder('Headers found in the uploaded CSV file are below.')
                                        ->visible(fn(Forms\Get $get) => $get('csv_headers_available'))
                                        ->disabled()
                                        ->dehydrated(true)
                                        ->tagPrefix('[[')
                                        ->tagSuffix(']]')
                                        ->hint('Use these headers in your email templates.'),
                                    Forms\Components\Toggle::make('has_personalized_attachments')
                                        ->label('Enable Personalized Attachment?')
                                        ->reactive(),
                                    Forms\Components\Toggle::make('tracking_enabled')
                                        ->label('Enable Email Open/Click Tracking?')
                                        ->default(false),
                                ])
                                ->columnSpan(1),

                        ])->columns(4),
                    Forms\Components\Section::make('Email Connection Configuration')
                        ->description('Select the SMTP configuration to use for sending emails.')
                        ->schema([
                            Forms\Components\Select::make('smtp_configuration_id')
                                ->label('Select SMTP Configuration')
                                ->relationship(
                                    name: 'smtpConfiguration',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn(Builder $query) => $query->where('user_id', Auth::id()) // Filter by user
                                )
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                ])->columnSpanFull()
                ->extraAttributes(['style' => 'background-color:#ccc'])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Batch Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('smtpConfiguration.name')
                    ->label('SMTP Config')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => fn($state): bool => in_array($state, ['generating', 'sending']),
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('generated_count')
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
                    ->toggleable(isToggledHiddenByDefault: false),
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
                    ->relationship('smtpConfiguration', 'name', fn(Builder $query) => $query->where('user_id', Auth::id())),

            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('clone')
                        ->label('Clone (Coming Soon)')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->url(fn (EmailBatch $record): string => static::getUrl('create', ['clone_from' => $record->id])),
                    Tables\Actions\Action::make('generate')
                        ->label('Generate')
                        ->icon('heroicon-o-play-circle') // Or 'heroicon-o-arrow-path' for retry
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Emails')
                        ->modalDescription('Start generating emails for this batch?')
                        ->action(function (EmailBatch $record) {
                            // Double-check status
                            if (!in_array($record->status, ['draft', 'failed'])) {
                                 Notification::make()
                                    ->title('Cannot Generate')
                                    ->body('Emails can only be generated for batches in draft or failed status.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $record->update(['status' => 'generating', 'generated_count' => 0, 'failed_count' => 0]); // Reset counts
                            GenerateBatchEmailsJob::dispatch($record);

                            Notification::make()
                                ->title('Batch Generation Started')
                                ->body('The batch generation process has started for batch ID: ' . $record->id)
                                ->success()
                                ->send();
                        })
                        ->visible(fn (EmailBatch $record): bool => in_array($record->status, ['draft', 'failed'])), // Show only for draft or failed

                    // Add the Send Emails Action
                    Tables\Actions\Action::make('sendEmails')
                        ->label('Send Emails')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send Batch Emails')
                        ->modalDescription('Are you sure you want to start sending emails for this batch?')
                        ->action(function (EmailBatch $record) {
                            // Check if the batch is ready to be sent
                            if ($record->status !== 'generated') {
                                Notification::make()
                                    ->title('Cannot Send')
                                    ->body('Emails can only be sent for batches with status "generated".')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Update status and dispatch the sending job
                            $record->update(['status' => 'sending']); // Or 'queued_sending'
                            SendGeneratedEmailsJob::dispatch($record->id); // Dispatch the job to handle sending

                            Notification::make()->success()->title('Sending Started')->body('The email sending process has been initiated for batch ID: ' . $record->id)->send();
                        })
                        ->visible(fn (EmailBatch $record): bool => $record->status === 'generated'), // Only show if emails are generated
                ]),
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
            RelationManagers\EmailsRelationManager::class,
            RelationManagers\EmailTrackingEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailBatches::route('/'),
            'create' => Pages\CreateEmailBatch::route('/create'),
            'view' => Pages\ViewEmailBatch::route('/{record}'),
            'edit' => Pages\EditEmailBatch::route('/{record}/edit'),
        ];
    }
}
