<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Spatie\Permission\Models\Role;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Collection;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role as ModelsRole;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->required()
                    ->maxLength(255),
                TextInput::make('civiled_id')
                    ->required()
                    ->unique(User::class, 'civiled_id', ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('cr_number')
                    ->required()
                    ->unique(User::class, 'cr_number', ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->required()
                    ->maxLength(12),
            TextInput::make('postal_code')
                ->required()
                ->maxLength(12),
            Toggle::make('is_admin')
                ->required(),
            Select::make('country_id')
                ->relationship(name: 'country', titleAttribute: 'en_name')
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Select::make('region_id')
                ->relationship(name: 'region', titleAttribute: 'en_name')
                ->options(fn(Get $get): Collection => Region::query()
                    ->where('country_id', $get('country_id'))
                    ->pluck('en_name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            Select::make('city_id')
                ->relationship(name: 'city', titleAttribute: 'en_name')
                ->options(fn(Get $get): Collection => City::query()
                    ->where('region_id', $get('region_id'))
                    ->pluck('en_name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            Select::make('type')
                ->options(function () {
                    $enumValues = DB::select("SHOW COLUMNS FROM users WHERE Field = 'type'")[0]->Type;
                    preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
                    $values = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

                    return array_combine($values, $values);
                }),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                Select::make('roles')
                    ->label('Roles')
                    //->options(Role::all()->pluck('name', 'name'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->options(ModelsRole::all()->pluck('name', 'id'))
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                BadgeColumn::make('roles.name')
                    ->label('Roles')
                    ->color('primary') // Customize the badge color
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
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
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->slideOver(),
                ])
            ])
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
