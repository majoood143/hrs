<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\City;
use App\Models\Region;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

/**
 * Admin users, stable owners and the other account types. What someone may do in /admin comes
 * from their Shield roles; `type = stable_owner` sends them to the /stable panel instead
 * (`User::canAccessPanel()`).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function getModelLabel(): string
    {
        return __('admin_user.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_user.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_user.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return collect(User::TYPES)->mapWithKeys(fn (string $type) => [$type => __("admin_user.types.{$type}")])->all();
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return (bool) $user?->hasRole(static::superAdminRole());
    }

    public static function superAdminRole(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }

    public static function form(Schema $schema): Schema
    {
        $nameColumn = app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';

        return $schema
            ->components([
                Section::make(__('admin_user.sections.account'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin_user.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('admin_user.fields.email'))
                            ->email()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('admin_user.fields.phone'))
                            ->helperText(__('admin_user.fields.phone_helper'))
                            ->tel()
                            ->maxLength(20)
                            // stored like everywhere else: digits with the country code
                            ->dehydrateStateUsing(fn (?string $state) => PhoneNumber::normalize($state)),
                        Select::make('locale')
                            ->label(__('admin_user.fields.locale'))
                            ->helperText(__('admin_user.fields.locale_helper'))
                            ->options(collect(config('languages.available'))->map(fn (array $meta) => $meta['name'])->all())
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make(__('admin_user.sections.access'))
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Select::make('type')
                            ->label(__('admin_user.fields.type'))
                            ->helperText(__('admin_user.fields.type_helper'))
                            ->options(static::typeOptions())
                            ->default('owner')
                            ->required()
                            ->native(false)
                            // changing your own type to stable owner would lock you out of /admin
                            ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                        Select::make('roles')
                            ->label(__('admin_user.fields.roles'))
                            ->helperText(__('admin_user.fields.roles_helper'))
                            ->relationship(
                                'roles',
                                'name',
                                // only a super admin may hand out the super admin role
                                modifyQueryUsing: fn (Builder $query) => static::isSuperAdmin(auth()->user())
                                    ? $query
                                    : $query->where('name', '!=', static::superAdminRole()),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            // a hidden super_admin role would be detached on save, so leave the field alone
                            ->disabled(fn (?User $record) => $record !== null
                                && static::isSuperAdmin($record)
                                && ! static::isSuperAdmin(auth()->user())),
                        DateTimePicker::make('email_verified_at')
                            ->label(__('admin_user.fields.email_verified_at')),
                        DateTimePicker::make('phone_verified_at')
                            ->label(__('admin_user.fields.phone_verified_at'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view']),
                    ])
                    ->columns(2),

                Section::make(__('admin_user.sections.password'))
                    ->icon('heroicon-o-key')
                    ->description(fn (string $operation) => $operation === 'edit' ? __('admin_user.fields.password_helper') : null)
                    ->schema([
                        TextInput::make('password')
                            ->label(__('admin_user.fields.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->required(fn (string $operation) => $operation === 'create')
                            // blank on edit = keep the current one (the model's cast hashes it)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->maxLength(255),
                        TextInput::make('password_confirmation')
                            ->label(__('admin_user.fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->requiredWith('password')
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->hiddenOn('view'),

                Section::make(__('admin_user.sections.identity'))
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('civiled_id')
                            ->label(__('admin_user.fields.civiled_id'))
                            ->unique(User::class, 'civiled_id', ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('cr_number')
                            ->label(__('admin_user.fields.cr_number'))
                            ->unique(User::class, 'cr_number', ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('country_id')
                            ->label(__('admin_user.fields.country'))
                            ->relationship('country', $nameColumn)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('region_id', null);
                                $set('city_id', null);
                            }),
                        Select::make('region_id')
                            ->label(__('admin_user.fields.region'))
                            ->options(fn (Get $get) => Region::query()
                                ->where('country_id', $get('country_id'))
                                ->orderBy($nameColumn)
                                ->pluck($nameColumn, 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label(__('admin_user.fields.city'))
                            ->options(fn (Get $get) => City::query()
                                ->where('region_id', $get('region_id'))
                                ->orderBy($nameColumn)
                                ->pluck($nameColumn, 'id'))
                            ->searchable(),
                        TextInput::make('postal_code')
                            ->label(__('admin_user.fields.postal_code'))
                            ->maxLength(12),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles')->withCount('stables'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_user.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin_user.fields.email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label(__('admin_user.fields.phone'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('admin_user.fields.type'))
                    ->formatStateUsing(fn (?string $state) => $state ? __("admin_user.types.{$state}") : null)
                    ->badge()
                    ->color(fn (?string $state) => $state === User::TYPE_STABLE_OWNER ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('admin_user.fields.roles'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('stables_count')
                    ->label(__('admin_user.fields.stables'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (int $state) => $state ?: '—')
                    ->toggleable(),
                IconColumn::make('email_verified_at')
                    ->label(__('admin_user.fields.verified'))
                    ->boolean()
                    ->getStateUsing(fn (User $record) => $record->email_verified_at !== null)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('admin_user.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin_user.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('admin_user.fields.roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('email_verified_at')
                    ->label(__('admin_user.fields.verified'))
                    ->nullable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->slideOver(),
                ]),
            ])
            // nobody deletes their own account from here
            ->checkIfRecordIsSelectableUsing(fn (User $record) => ! $record->is(auth()->user()))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
