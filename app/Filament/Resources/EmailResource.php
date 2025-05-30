<?php

namespace App\Filament\Resources;

use AmidEsfahani\FilamentTinyEditor\TinyEditor; // <-- Add TinyEditor if editing body
use App\Filament\Resources\EmailResource\Pages;
use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth; // <-- Add Auth
use Filament\Tables\Actions\Action; // <-- Add Action for custom actions
// Add Job for sending single email if you create one
// use App\Jobs\SendSingleEmailJob;
use Filament\Notifications\Notification; // <-- Add Notification

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope'; // Use a different icon from batch

    protected static ?string $navigationGroup = 'Email Campaigns'; // Group with EmailBatch

    // Optional: Hide from main navigation if you only want to access via Relation Manager
    // protected static bool $shouldRegisterNavigation = false;

    // Scope queries to the logged-in user
    public static function getEloquentQuery(): Builder
    {
        // Ensure user relationship exists and is loaded correctly if needed for filtering
        // This assumes the Email model has a direct user_id or via batch
        return parent::getEloquentQuery()
            ->whereHas('email_batch', function ($query) {
                $query->where('user_id', Auth::id());
            });
        // Or if Email has user_id directly:
        // return parent::getEloquentQuery()->where('user_id', Auth::id());
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Email Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('email_batch_id')
                            ->relationship('email_batch', 'id') // Display batch ID or maybe a name/subject?
                            ->label('Email Batch')
                            ->disabled()
                            ->required(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('to_address')
                            ->label('To')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('cc_address')
                            ->label('CC')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bcc_address')
                            ->label('BCC')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TinyEditor::make('body')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('attachments') // Display attachments as KeyValue or custom component
                            ->label('Attachments (Paths)')
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false)
                            ->editableValues(false) // Make read-only for viewing
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->disabled(),
                        Forms\Components\Textarea::make('error_message')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email_batch.id') // Or email_batch.subject if more descriptive
                    ->label('Batch ID')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('to_address')
                    ->label('To')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(50) // Limit subject length for display
                    ->tooltip(fn (Email $record): string => $record->subject) // Show full subject on hover
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->searchable()
                    ->sortable(),
                 Tables\Columns\IconColumn::make('attachments')
                    ->label('Has Attachments')
                    ->boolean()
                    ->getStateUsing(fn (Email $record): bool => !empty($record->attachments)), // Check if attachments array is not empty
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
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
                 Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
                 Tables\Filters\SelectFilter::make('email_batch_id')
                    ->label('Email Batch')
                    ->relationship('email_batch', 'id'), // Adjust 'id' if you want to filter by batch name/subject
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    // Optionally restrict editing based on status
                    ->visible(fn (Email $record): bool => $record->status === 'pending' || $record->status === 'failed'),
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Email')
                    ->modalDescription('Are you sure you want to send this email now?')
                    ->visible(fn (Email $record): bool => $record->status === 'pending' || $record->status === 'failed') // Only show for pending/failed
                    ->action(function (Email $record) {
                        // --- Logic to send a single email ---
                        // 1. Check if SMTP config is available (via $record->email_batch->smtpConfiguration)
                        // 2. Create a Mailable instance with data from $record
                        // 3. Add attachments from $record->attachments
                        // 4. Use Mail::mailer(...)->send(...) with the correct config
                        // 5. Update $record->status to 'sent' or 'failed'
                        // 6. Record sent_at or error_message

                        // Example (needs refinement with actual sending logic/job):
                        try {
                            // --- Placeholder for actual sending logic ---
                            // dispatch(new SendSingleEmailJob($record->id)); // Recommended approach
                            // --- Simulate success for now ---
                            $record->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                            Notification::make()
                                ->title('Email Queued/Sent')
                                ->body("Email to {$record->to_address} was sent successfully.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                             $record->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                             Notification::make()
                                ->title('Send Failed')
                                ->body("Failed to send email to {$record->to_address}: " . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Add Bulk Send Action if needed
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Potentially add relation to the specific EmailBatch? Usually not needed here.
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmails::route('/'),
            // 'create' => Pages\CreateEmail::route('/create'), // Usually emails are created via batch
            //'view' => Pages\ViewEmail::route('/{record}'),
            'edit' => Pages\EditEmail::route('/{record}/edit'),
        ];
    }
}
