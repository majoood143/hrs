<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorseResource\Pages;
use App\Filament\Resources\HorseResource\RelationManagers;
use App\Models\City;
use App\Models\Horse;
use App\Models\Region;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportBulkAction as ActionsExportBulkAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\HorseResource\RelationManagers\AttachementRelationManager;
use App\Filament\Resources\HorseResource\RelationManagers\VaccinationRelationManager;
use App\Filament\Resources\HorseResource\RelationManagers\TransactionRelationManager;
use App\Models\Type;
use App\Models\Vaccination;

class HorseResource extends Resource
{
    protected static ?string $model = Horse::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Tabs::make('Create Horse')
                    ->tabs([
                        Tab::make('Horse Information')
                            ->schema([

                                Forms\Components\TextInput::make('en_name')

                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('The name will be used in the QR code and certificate'),
                                Forms\Components\TextInput::make('ar_name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('microchip')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('passport_number')
                                    ->required()
                                    ->maxLength(255),
                                // Forms\Components\TextInput::make('age')
                                //     ->required()
                                //     ->numeric(),
                                Forms\Components\DatePicker::make('dob')
                                    ->required(),
                                // Forms\Components\TextInput::make('height')
                                //     ->required()
                                //     ->numeric(),
                                Forms\Components\Select::make('type_id')
                                    ->relationship(name: 'type', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),

                                Forms\Components\Select::make('gender_id')
                                    ->relationship(name: 'gender', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                Forms\Components\Select::make('dam_id')
                                    ->relationship(name: 'horse', titleAttribute: 'en_name')
                                    ->options(fn(Get $get): Collection => Horse::query()
                                        ->where('type_id', 2)
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Dam (Mother)'),
                                Forms\Components\Select::make('sire_id')
                                    ->relationship(name: 'horse', titleAttribute: 'en_name')
                                    ->options(fn(Get $get): Collection => Horse::query()
                                        ->where('type_id', 1)
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Sire (Father)'),
                                // Forms\Components\Select::make('color_id')
                                //     ->relationship(name: 'color', titleAttribute: 'en_name')
                                //     ->searchable()
                                //     ->preload()
                                //     ->required(),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(3),
                        Tab::make('Location')
                            ->schema([
                                Forms\Components\Select::make('country_id')
                                    ->relationship(name: 'country', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                Forms\Components\Select::make('region_id')
                                    ->relationship(name: 'region', titleAttribute: 'en_name')
                                    ->options(fn(Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('city_id')
                                    ->relationship(name: 'city', titleAttribute: 'en_name')
                                    ->options(fn(Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make('Owner Information')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->relationship(name: 'user', titleAttribute: 'name')
                                    ->options(fn(Get $get): Collection => User::query()
                                        ->where('type', 'owner')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->label('Owner')
                                    ->helperText('The owner will be able to manage the horse from his account')
                                    ->placeholder('Select Owner')
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('password')
                                            ->password()
                                            ->required()
                                            ->maxLength(255),
                                    ])

                            ])->icon('heroicon-o-user')
                            ->columns(2),
                        Tab::make('Attachments')
                            ->schema([
                                FileUpload::make('attachment')
                                    ->multiple(),
                            ])->icon('heroicon-o-folder-open')
                            ->columns(3),
                        Tab::make('Vaccinations')
                            ->schema([])->icon('heroicon-o-puzzle-piece'),
                        Tab::make('Transactions')
                            ->schema([])->icon('heroicon-o-queue-list'),
                    ])->columnSpanFull(),


                // Forms\Components\Select::make('country_id')
                //     ->relationship(name: 'country', titleAttribute: 'en_name')
                //     ->searchable()
                //     ->preload()
                //     ->live()
                //     ->required(),
                // Forms\Components\Select::make('region_id')
                //     ->relationship(name: 'region', titleAttribute: 'en_name')
                //     ->options(fn(Get $get): Collection => Region::query()
                //         ->where('country_id', $get('country_id'))
                //         ->pluck('en_name', 'id'))
                //     ->searchable()
                //     ->preload()
                //     ->required(),
                // Forms\Components\Select::make('city_id')
                //     ->relationship(name: 'city', titleAttribute: 'en_name')
                //     ->options(fn(Get $get): Collection => City::query()
                //         ->where('region_id', $get('region_id'))
                //         ->pluck('en_name', 'id'))
                //     ->searchable()
                //     ->preload()
                //     ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('en_name')
                    ->searchable()
                    ->description(fn(Horse $record): string => "Microchip: {$record->microchip}, Passport: {$record->passport_number}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('ar_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->suffix(' years')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city.en_name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('region.en_name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country.en_name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type.en_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender.en_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Owner'),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([

                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                    Action::make('printQR')
                        ->label('Print QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('success')
                        ->action(function ($record) {
                            // Encode text to be safe for URL
                            $encodedText = urlencode($record->microchip);

                            // QuickChart QR URL
                            $qrCodeUrl = "https://quickchart.io/qr?text={$encodedText}&size=300";

                            // Stream the file directly to the user
                            return response()->streamDownload(function () use ($qrCodeUrl) {
                                echo file_get_contents($qrCodeUrl);
                            }, 'horse-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $record->microchip) . '.png', [
                                'Content-Type' => 'image/png',
                            ]);
                        }),




                    Action::make('printPassport')
                        ->label('Print passport')
                        ->icon('heroicon-o-printer'),
                    Action::make('lostPassport')
                        ->label('Lost passport')
                        ->icon('heroicon-o-arrow-uturn-down')
                        ->color('danger')
                        ->form([
                            Forms\Components\DatePicker::make('lost_passport_date')
                                ->required()
                                ->label('Lost passport date'),
                            Forms\Components\TextInput::make('lost_passport_reason')
                                ->required()
                                ->maxLength(65535)
                                ->label('Lost passport reason'),
                        ]),


                    Action::make('changeOwner')
                        ->label('Change owner')
                        ->icon('heroicon-o-user-circle')
                        ->color('warning')
                        ->modalWidth('xl')
                        ->modalHeading(fn($record) => "Transfer Ownership: {$record->user?->name}")
                        ->form([
                            Forms\Components\Grid::make(2)
                                ->schema([

                                    Forms\Components\Section::make('Current Owner Details')
                                        ->schema([
                                            Forms\Components\Placeholder::make('user.name')
                                                ->label('Current Owner')
                                                ->content(fn($record) => $record->user?->name ?? 'Unassigned'),
                                            Forms\Components\Placeholder::make('user.email')
                                                ->label('Email')
                                                ->content(fn($record) => $record->user?->email ?? 'Unassigned'),
                                            Forms\Components\Placeholder::make('user.phone')
                                                ->label('Mobile')
                                                ->content(fn($record) => $record->user?->phone ?? 'Unassigned'),
                                        ])
                                        ->columns(2),
                                    //->content(fn (Horse $record): string => $record->user->name),
                                    Forms\Components\Section::make('New Owner Details')
                                        ->schema([
                                            Forms\Components\Select::make('user_id')
                                                ->relationship(name: 'user', titleAttribute: 'name')
                                                ->options(fn(Get $get): Collection => User::query()
                                                    ->where('type', 'owner')
                                                    ->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->required()
                                                ->label('Owner')
                                                ->helperText('The owner will be able to manage the horse from his account')
                                                ->placeholder('Select Owner')

                                                ->createOptionForm([
                                                    Forms\Components\TextInput::make('name')
                                                        ->required()
                                                        ->maxLength(255),
                                                    Forms\Components\TextInput::make('email')
                                                        ->email()
                                                        ->required()
                                                        ->maxLength(255),
                                                    Forms\Components\TextInput::make('password')
                                                        ->password()
                                                        ->required()
                                                        ->maxLength(255),
                                                ])
                                        ])
                                ])
                        ])
                        ->action(function (array $data, $record): void {
                            $record->update(['user_id' => $data['user_id']]);
                        }),
                    //->successNotificationTitle('Horse owner updated successfully'),
                    Action::make('exportHorse')
                        ->label('Export Horse')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Grid::make(2)
                                ->schema([

                                    Forms\Components\Section::make('Current Location Details')
                                        ->schema([
                                            Forms\Components\Placeholder::make('country.en_name')
                                                ->label('Country')
                                                ->content(fn($record) => $record->country?->en_name ?? 'Unassigned'),
                                            Forms\Components\Placeholder::make('region.en_name')
                                                ->label('Region')
                                                ->content(fn($record) => $record->region?->en_name ?? 'Unassigned'),
                                            Forms\Components\Placeholder::make('city.en_name')
                                                ->label('City')
                                                ->content(fn($record) => $record->city?->en_name ?? 'Unassigned'),
                                        ])->columns(3),
                                    Forms\Components\Section::make('Export Location Details')
                                        ->schema([
                                            Forms\Components\Select::make('country_id')
                                                ->relationship(name: 'country', titleAttribute: 'en_name')
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->required(),
                                            Forms\Components\Select::make('region_id')
                                                ->relationship(name: 'region', titleAttribute: 'en_name')
                                                ->options(fn(Get $get): Collection => Region::query()
                                                    ->where('country_id', $get('country_id'))
                                                    ->pluck('en_name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                            Forms\Components\Select::make('city_id')
                                                ->relationship(name: 'city', titleAttribute: 'en_name')
                                                ->options(fn(Get $get): Collection => City::query()
                                                    ->where('region_id', $get('region_id'))
                                                    ->pluck('en_name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                        ])->columns(3),
                                ]),
                        ])
                        ->action(function (array $data, $record): void {
                            $record->update([

                                'country_id' => $data['country_id'],
                                'region_id' => $data['region_id'],
                                'city_id' => $data['city_id']
                            ]);
                        })
                    ->successNotificationTitle('Horse Export updated successfully'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),


            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
            AttachementRelationManager::class,
            VaccinationRelationManager::class,
            TransactionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorses::route('/'),
            'create' => Pages\CreateHorse::route('/create'),
            'view' => Pages\ViewHorse::route('/{record}'),
            'edit' => Pages\EditHorse::route('/{record}/edit'),
        ];
    }
}
