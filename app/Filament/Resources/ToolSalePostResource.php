<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolSalePostResource\Pages\CreateToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages\EditToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages\ListToolSalePosts;
use App\Filament\Resources\ToolSalePostResource\Pages\ViewToolSalePost;
use App\Filament\Support\PendingPosts;
use App\Filament\Support\PlaceFormFields;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\SiteSetting;
use App\Models\ToolSalePost;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ToolSalePostResource extends Resource
{
    protected static ?string $model = ToolSalePost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_tool_sale_post.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_tool_sale_post.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_tool_sale_post.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return PendingPosts::navigationBadge(static::$model);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PendingPosts::navigationBadgeColor(static::$model);
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return PendingPosts::navigationBadgeTooltip(static::$model);
    }

    /** `ar_name` in the Arabic admin, else `en_name` (locations, posts). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tool Sale Post')
                    ->tabs([
                        Tab::make(__('admin_tool_sale_post.tabs.info'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(__('tools-for-sale.name_en'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->label(__('tools-for-sale.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Select::make('category')
                                    ->label(__('tools-for-sale.category'))
                                    ->options(fn () => ToolSalePost::categoryOptions())
                                    ->required(),
                                Select::make('condition')
                                    ->label(__('tools-for-sale.condition'))
                                    ->options(fn () => ToolSalePost::conditionOptions())
                                    ->required(),
                                TextInput::make('brand')
                                    ->label(__('tools-for-sale.brand'))
                                    ->maxLength(255),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('admin_tool_sale_post.tabs.location'))
                            ->schema([
                                Select::make('country_id')
                                    ->label(__('tools-for-sale.country'))
                                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('region_id', null);
                                        $set('city_id', null);
                                    })
                                    ->required(),
                                Select::make('region_id')
                                    ->label(__('tools-for-sale.region'))
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('tools-for-sale.city'))
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->required(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('admin_tool_sale_post.tabs.listing'))
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('tools-for-sale.price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn () => SiteSetting::currencyHtml())
                                    ->required(),
                                Toggle::make('price_negotiable')
                                    ->label(__('tools-for-sale.price_negotiable'))
                                    ->inline(false),
                                Select::make('status')
                                    ->label(__('admin_tool_sale_post.fields.status'))
                                    ->options(fn () => ToolSalePost::statusOptions())
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->label(__('tools-for-sale.contact_number'))
                                    ->tel()
                                    ->maxLength(32)
                                    ->required(),
                                PlaceFormFields::coverPhoto('tools-for-sale/covers', __('tools-for-sale.cover_photo'))
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->label(__('admin_tool_sale_post.fields.description_en'))
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->label(__('admin_tool_sale_post.fields.description_ar'))
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-currency-dollar')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            // The city column is eager loaded by Filament; the country under it is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('tools-for-sale.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label(__('admin_tool_sale_post.fields.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('tools-for-sale.category'))
                    ->formatStateUsing(fn (string $state): string => ToolSalePost::categoryOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('condition')
                    ->label(__('tools-for-sale.condition'))
                    ->formatStateUsing(fn (string $state): string => ToolSalePost::conditionOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('tools-for-sale.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->description(fn (ToolSalePost $record): ?string => $record->price_negotiable ? __('tools-for-sale.price_negotiable') : null)
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(__('tools-for-sale.city'))
                    ->description(fn (ToolSalePost $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('status')
                    ->label(__('admin_tool_sale_post.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ToolSalePost::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        PendingPosts::STATUS => 'warning',
                        'sold' => 'info',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_tool_sale_post.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_tool_sale_post.fields.status'))
                    ->options(fn () => ToolSalePost::statusOptions()),
                SelectFilter::make('category')
                    ->label(__('tools-for-sale.category'))
                    ->options(fn () => ToolSalePost::categoryOptions()),
                SelectFilter::make('condition')
                    ->label(__('tools-for-sale.condition'))
                    ->options(fn () => ToolSalePost::conditionOptions()),
                SelectFilter::make('country_id')
                    ->label(__('tools-for-sale.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                PendingPosts::approveAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (ToolSalePost $record): string => route('tools-for-sale.show', $record->id),
                        isPublic: fn (ToolSalePost $record): bool => $record->status === 'active',
                    ),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PendingPosts::approveBulkAction(),
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToolSalePosts::route('/'),
            'create' => CreateToolSalePost::route('/create'),
            'view' => ViewToolSalePost::route('/{record}'),
            'edit' => EditToolSalePost::route('/{record}/edit'),
        ];
    }
}
