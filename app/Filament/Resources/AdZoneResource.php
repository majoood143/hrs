<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdZoneResource\Pages\CreateAdZone;
use App\Filament\Resources\AdZoneResource\Pages\EditAdZone;
use App\Filament\Resources\AdZoneResource\Pages\ListAdZones;
use App\Filament\Support\TranslatableInput;
use App\Models\AdZone;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AdZoneResource extends Resource
{
    protected static ?string $model = AdZone::class;
    protected static ?string $slug = 'promotion-zones';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?int $navigationSort = 93;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('ad_zones.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ad_zones.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('ad_zones.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("name.{$code}")
                    ->label(__('ad_zones.fields.name') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale())
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) use ($code) {
                        if ($code === TranslatableInput::defaultLocale()) {
                            $set('slug', Str::slug($state));
                        }
                    })),

                TextInput::make('slug')
                    ->label(__('ad_zones.fields.slug'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('order')
                    ->label(__('ad_zones.fields.order'))
                    ->helperText(__('ad_zones.fields.order_helper'))
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label(__('ad_zones.fields.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('ad_zones.fields.name'))
                    ->getStateUsing(fn (AdZone $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name->en', 'name->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('order')
                    ->label(__('ad_zones.fields.order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('ad_zones.fields.is_active'))
                    ->boolean(),

                TextColumn::make('ads_count')
                    ->label(__('ad_zones.navigation.ads'))
                    ->counts('ads'),
            ])
            ->defaultSort('order')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->disabled(fn (AdZone $record) => $record->ads()->exists()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('ad_zones.empty_state.heading'))
            ->emptyStateDescription(__('ad_zones.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdZones::route('/'),
            'create' => CreateAdZone::route('/create'),
            'edit' => EditAdZone::route('/{record}/edit'),
        ];
    }
}
