<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages\CreatePartner;
use App\Filament\Resources\PartnerResource\Pages\EditPartner;
use App\Filament\Resources\PartnerResource\Pages\ListPartners;
use App\Models\Partner;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 92;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('partner.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('partner.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('partner.sections.information'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('en_name')
                                ->label(__('partner.fields.name_en'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('ar_name')
                                ->label(__('partner.fields.name_ar'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label(__('partner.fields.type'))
                                ->options([
                                    'stable' => __('partner.types.stable'),
                                    'transport' => __('partner.types.transport'),
                                    'veterinary' => __('partner.types.veterinary'),
                                    'authority' => __('partner.types.authority'),
                                ])
                                ->default('stable')
                                ->native(false)
                                ->required(),

                            TextInput::make('website_url')
                                ->label(__('partner.fields.website_url'))
                                ->url()
                                ->maxLength(255),
                        ]),

                        Grid::make(2)->schema([
                            Textarea::make('en_description')
                                ->label(__('partner.fields.description_en'))
                                ->rows(3),

                            Textarea::make('ar_description')
                                ->label(__('partner.fields.description_ar'))
                                ->rows(3)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),

                        FileUpload::make('logo')
                            ->label(__('partner.fields.logo'))
                            ->image()
                            ->disk('public')
                            ->directory('partners'),

                        Grid::make(2)->schema([
                            Toggle::make('is_active')
                                ->label(__('partner.fields.is_active'))
                                ->default(true),

                            TextInput::make('order')
                                ->label(__('partner.fields.order'))
                                ->numeric()
                                ->default(0),
                        ]),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label(__('partner.columns.logo'))
                    ->disk('public'),

                TextColumn::make('en_name')
                    ->label(__('partner.columns.name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label(__('partner.columns.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("partner.types.{$state}")),

                IconColumn::make('is_active')
                    ->label(__('partner.columns.is_active'))
                    ->boolean(),

                TextColumn::make('order')
                    ->label(__('partner.columns.order'))
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('partner.filters.type'))
                    ->options([
                        'stable' => __('partner.types.stable'),
                        'transport' => __('partner.types.transport'),
                        'veterinary' => __('partner.types.veterinary'),
                        'authority' => __('partner.types.authority'),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('partner.actions.create_first')),
            ])
            ->emptyStateHeading(__('partner.empty_state.heading'))
            ->emptyStateDescription(__('partner.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
