<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use App\Models\Service;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Attachement;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\Alignment;
use JaOcero\RadioDeck\Forms\Components\RadioDeck;
use App\Filament\Resources\Model\attachments;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

// Define the Transaction resource class
class TransactionResource extends Resource
{
    // Specify the model associated with this resource
    protected static ?string $model = Transaction::class;

    // Define the navigation icon for this resource
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    // Get the navigation badge count
    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    // Define the form schema for creating or editing transactions
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                // Create a wizard form for transaction details
                Wizard::make([
                    // Define the first step of the wizard
                    Step::make('Transaction Details')
                        ->schema([
                            // Create a section for transaction information
                            Section::make('Transaction Information')
                                ->schema([

                        Select::make('service_id')
                            ->relationship(name: 'service', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(fn(callable $set, $state) => $set('amount', $state ? Service::find($state)->price : null))
                            ->live()
                            ->required(),
                        TextInput::make('amount')
                            ->prefix('OMR')
                            //->required()
                            //->disabled(),
                            ->numeric(3),
                        TextInput::make('currency')
                            ->default('OMR')
                            ->required()
                            ->maxLength(3),
                                    
                                    // Input for transaction name
                                    // Forms\Components\TextInput::make('name')
                                    //     ->maxLength(255)
                                    //     ->default(null),
                                    // Input for reference number
                                    TextInput::make('reference_number')
                                        ->maxLength(255)
                                        ->default(null),
                                    // Date picker for transaction date
                                    DatePicker::make('transaction_date')
                                        ->default(now())
                                        ->hidden()
                                        ->required(),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),
                   
                    // Additional steps can be added here
                    Step::make('Additional Information')
                        ->schema([
                            Section::make('Additional Information')
                                ->schema([

                        Group::make()
                            ->schema(function (callable $get) {
                                $serviceId = $get('service_id');

                                // Example logic — replace with your real service logic
                                switch ($serviceId) {
                                    case 1 : // e.g. Horse Registration
                                        return [
                                        TextInput::make('horse_name')
                                                ->label('Horse Name')
                                                ->required(),
                                        DatePicker::make('birth_date')
                                                ->label('Birth Date')
                                                ->required(),
                                        ];

                                    case 2: // e.g. Ownership Transfer
                                        return [
                                        Select::make('horse_id')
                                            ->label('Horse')
                                            ->relationship(name: 'horse', titleAttribute: 'en_name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        TextInput::make('previous_owner')
                                                ->label('Previous Owner')
                                                ->required(),
                                        TextInput::make('new_owner')
                                                ->label('New Owner')
                                                ->required(),
                                        ];

                                    case 3: // e.g. Microchip Replacement
                                        return [
                                        TextInput::make('old_microchip')
                                                ->label('Old Microchip ID')
                                                ->required(),
                                        TextInput::make('new_microchip')
                                                ->label('New Microchip ID')
                                                ->required(),
                                        ];

                                    default:
                                        return [
                                            Placeholder::make('no_service')
                                                ->content('Please select a service to show its fields.'),
                                        ];
                                }
                            })
                            ->reactive(),
                                    // Input for transaction description
                                    RichEditor::make('description')
                                        ->maxLength(255)
                                        ->default(null),
                                    
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                // Additional steps can be added here
                Step::make('Service Details')
                    ->schema([
                        Section::make('Service Information')
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
                    Step::make('Horse & Service')
                        ->schema([
                            Section::make('Attachments')
                                ->schema([
                                    Repeater::make('attachement')
                                        ->relationship()
                                        ->schema([
                                            TextInput::make('name')
                                                ->maxLength(255)
                                                ->default(null),
                                            RichEditor::make('description')
                                                ->maxLength(255)
                                                ->default(null),
                                            FileUpload::make('file_path')
                                                ->required(),
                                            //->maxLength(255),
                                            Select::make('type')
                                                ->options(function () {
                                                    $enumValues = DB::select("SHOW COLUMNS FROM attachements WHERE Field = 'type'")[0]->Type;
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


                Toggle::make('is_paid')
                    ->default(true)
                    ->required(),

            ]);
    }


    // Define the table schema for displaying transactions
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('horse.en_name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric(3)
                    ->prefix('OMR ')
                    ->sortable(),
                TextColumn::make('attachement_count')
                    ->counts('attachement')
                    ->badge(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Define actions for the table
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'view' => ViewTransaction::route('/{record}'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
