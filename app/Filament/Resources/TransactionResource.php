<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Attachement;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\Alignment;
use JaOcero\RadioDeck\Forms\Components\RadioDeck;
use App\Filament\Resources\Model\attachments;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Wizard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

// Define the Transaction resource class
class TransactionResource extends Resource
{
    // Specify the model associated with this resource
    protected static ?string $model = Transaction::class;

    // Define the navigation icon for this resource
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Get the navigation badge count
    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    // Define the form schema for creating or editing transactions
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // Create a wizard form for transaction details
                Wizard::make([
                    // Define the first step of the wizard
                    Wizard\Step::make('Transaction Details')
                        ->schema([
                            // Create a section for transaction information
                            Forms\Components\Section::make('Transaction Information')
                                ->schema([

                        Forms\Components\Select::make('service_id')
                            ->relationship(name: 'service', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(fn(callable $set, $state) => $set('amount', $state ? \App\Models\Service::find($state)->price : null))
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->prefix('OMR')
                            //->required()
                            //->disabled(),
                            ->numeric(3),
                        Forms\Components\TextInput::make('currency')
                            ->default('OMR')
                            ->required()
                            ->maxLength(3),
                                    
                                    // Input for transaction name
                                    // Forms\Components\TextInput::make('name')
                                    //     ->maxLength(255)
                                    //     ->default(null),
                                    // Input for reference number
                                    Forms\Components\TextInput::make('reference_number')
                                        ->maxLength(255)
                                        ->default(null),
                                    // Date picker for transaction date
                                    Forms\Components\DatePicker::make('transaction_date')
                                        ->default(now())
                                        ->hidden()
                                        ->required(),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),
                   
                    // Additional steps can be added here
                    Wizard\Step::make('Additional Information')
                        ->schema([
                            Forms\Components\Section::make('Additional Information')
                                ->schema([

                        Forms\Components\Group::make()
                            ->schema(function (callable $get) {
                                $serviceId = $get('service_id');

                                // Example logic — replace with your real service logic
                                switch ($serviceId) {
                                    case 1 : // e.g. Horse Registration
                                        return [
                                        Forms\Components\TextInput::make('horse_name')
                                                ->label('Horse Name')
                                                ->required(),
                                        Forms\Components\DatePicker::make('birth_date')
                                                ->label('Birth Date')
                                                ->required(),
                                        ];

                                    case 2: // e.g. Ownership Transfer
                                        return [
                                        Forms\Components\Select::make('horse_id')
                                            ->label('Horse')
                                            ->relationship(name: 'horse', titleAttribute: 'en_name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Forms\Components\TextInput::make('previous_owner')
                                                ->label('Previous Owner')
                                                ->required(),
                                        Forms\Components\TextInput::make('new_owner')
                                                ->label('New Owner')
                                                ->required(),
                                        ];

                                    case 3: // e.g. Microchip Replacement
                                        return [
                                        Forms\Components\TextInput::make('old_microchip')
                                                ->label('Old Microchip ID')
                                                ->required(),
                                        Forms\Components\TextInput::make('new_microchip')
                                                ->label('New Microchip ID')
                                                ->required(),
                                        ];

                                    default:
                                        return [
                                            Forms\Components\Placeholder::make('no_service')
                                                ->content('Please select a service to show its fields.'),
                                        ];
                                }
                            })
                            ->reactive(),
                                    // Input for transaction description
                                    Forms\Components\RichEditor::make('description')
                                        ->maxLength(255)
                                        ->default(null),
                                    
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                // Additional steps can be added here
                Wizard\Step::make('Service Details')
                    ->schema([
                        Forms\Components\Section::make('Service Information')
                            ->schema([

                                // Forms\Components\TextInput::make('service_date')
                                //     ->label('Service Date')
                                //     ->default(now())
                                //     ->required(),
                                // Forms\Components\TextInput::make('due_date')
                                //     ->label('Due Date')
                                //     ->default(null)
                                //     ->required(),
                                // Forms\Components\TextInput::make('payment_date')
                                //     ->label('Payment Date')
                                //     ->default(null),
                            ])->columns(3)
                            ->columnSpanFull(),
                    ]),
                    // ])
                    //     ->columns(1)
                    //     ->columnSpanFull(),
                    // // Input for horse selection
                    // Wizard::make([
                    Wizard\Step::make('Horse & Service')
                        ->schema([
                            Forms\Components\Section::make('Attachments')
                                ->schema([
                                    Forms\Components\Repeater::make('attachement')
                                        ->relationship()
                                        ->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->maxLength(255)
                                                ->default(null),
                                            Forms\Components\RichEditor::make('description')
                                                ->maxLength(255)
                                                ->default(null),
                                            Forms\Components\FileUpload::make('file_path')
                                                ->required(),
                                            //->maxLength(255),
                                            Forms\Components\Select::make('type')
                                                ->options(function () {
                                                    $enumValues = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM attachements WHERE Field = 'type'")[0]->Type;
                                                    preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
                                                    $values = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

                                                    return array_combine($values, $values);
                                                })
                                                //->options(Attachement::class)
                                                ->required(),
                                        ])
                                        ->columns(3)
                                        ->default(null)
                                        ->collapsible()
                                        ->reorderableWithButtons()
                                        ->addActionAlignment(Alignment::Start)
                                        ->addActionLabel('Add Attachment')
                                        //->grid(2) // Arrange items in grid
                                        ->columns(2) // Number of columns in the grid
                                        ->columnSpanFull(),
                                ])
                        ])
                ])
                    ->columns(1)
                    ->columnSpanFull(),



                // Forms\Components\Select::make('user_id')
                //     ->relationship(name: 'user', titleAttribute: 'name')
                //     ->searchable()
                //     ->preload(),

                // Forms\Components\TextInput::make('currency')
                //     ->default('OMR')
                //     ->required()
                //     ->maxLength(3),


                Forms\Components\Toggle::make('is_paid')
                    ->default(true)
                    ->required(),

            ]);
    }


    // Define the table schema for displaying transactions
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('horse.en_name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric(3)
                    ->prefix('OMR ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachement_count')
                    ->counts('attachement')
                    ->badge(),
                Tables\Columns\TextColumn::make('description')
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
                // Define actions for the table
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // Define relationships for this resource
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // Define the pages for this resource
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
