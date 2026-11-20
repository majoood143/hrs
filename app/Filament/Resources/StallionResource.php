<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StallionResource\Pages\CreateStallion;
use App\Filament\Resources\StallionResource\Pages\EditStallion;
use App\Filament\Resources\StallionResource\Pages\ListStallions;
use App\Filament\Support\LocationFields;
use App\Filament\Support\PlaceFormFields;
use App\Models\Stallion;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Admin → Directory → Stallions: breeding stallions, their pedigree and race record. */
class StallionResource extends Resource
{
    protected static ?string $model = Stallion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('stallions.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stallions.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('stallions.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl'];

        return $schema
            ->components([
                Tabs::make('Stallion')
                    ->tabs([
                        Tab::make(__('stallions.tabs.info'))
                            ->schema([
                                TextInput::make('en_name')->label(__('stallions.name_en'))->placeholder('e.g. Bahar Muscat')->required()->maxLength(255),
                                TextInput::make('ar_name')->label(__('stallions.name_ar'))->placeholder('مثال: بحر مسقط')->required()->maxLength(255)->extraInputAttributes($rtl),
                                Select::make('colour')->label(__('stallions.colour'))->options(fn () => Stallion::colourOptions()),
                                TextInput::make('foaled_year')->label(__('stallions.foaled_year'))->placeholder('e.g. 2015')->integer()->minValue(1980)->maxValue((int) date('Y')),
                                TextInput::make('origin')->label(__('stallions.origin'))->helperText(__('stallions.origin_help'))->placeholder('OM')->maxLength(3),
                                Toggle::make('is_active')->label(__('stallions.is_active'))->default(true)->inline(false),
                                PlaceFormFields::coverPhoto('stallions', __('stallions.photo'))->columnSpanFull(),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('stallions.tabs.pedigree'))
                            ->schema([
                                TextInput::make('sire_en')->label(__('stallions.sire_en'))->placeholder('e.g. Amer (SA)')->maxLength(255),
                                TextInput::make('sire_ar')->label(__('stallions.sire_ar'))->placeholder('مثال: عامر')->maxLength(255)->extraInputAttributes($rtl),
                                TextInput::make('dam_en')->label(__('stallions.dam_en'))->placeholder('e.g. Nisaee (FR)')->maxLength(255),
                                TextInput::make('dam_ar')->label(__('stallions.dam_ar'))->placeholder('مثال: نسائي')->maxLength(255)->extraInputAttributes($rtl),
                                ...collect(Stallion::PEDIGREE_KEYS)->map(fn (string $key) => TextInput::make("pedigree.{$key}")
                                    ->label(__("stallions.ancestors.{$key}"))
                                    ->placeholder('e.g. Manganate (FR)')
                                    ->helperText($key === 'sire_sire' ? __('stallions.pedigree_help') : null)
                                    ->maxLength(255))->all(),
                                TextInput::make('pedigree_url')->label(__('stallions.pedigree_url'))->helperText(__('stallions.pedigree_url_help'))
                                    ->placeholder('https://www.allbreedpedigree.com/…')->url()->maxLength(500)->columnSpanFull(),
                            ])->icon('heroicon-o-share')
                            ->columns(2),
                        Tab::make(__('stallions.tabs.racing'))
                            ->schema([
                                TextInput::make('runs')->label(__('stallions.runs'))->placeholder('e.g. 16')->integer()->minValue(0),
                                TextInput::make('wins')->label(__('stallions.wins'))->placeholder('e.g. 5')->integer()->minValue(0),
                                TextInput::make('places')->label(__('stallions.places'))->placeholder('e.g. 5')->integer()->minValue(0),
                                TextInput::make('rating')->label(__('stallions.rating'))->placeholder('e.g. 111')->integer()->minValue(0),
                                TextInput::make('racing_horse_id')->label(__('stallions.racing_horse_id'))->helperText(__('stallions.racing_horse_id_help'))
                                    ->placeholder('e.g. 12345')->integer()->minValue(1)->columnSpanFull(),
                                Textarea::make('highlights_en')->label(__('stallions.highlights_en'))->placeholder('e.g. 1st Oman Derby 1600m')->rows(4),
                                Textarea::make('highlights_ar')->label(__('stallions.highlights_ar'))->placeholder('مثال: الأول في داربي عمان ١٦٠٠م')->rows(4)->extraInputAttributes($rtl),
                            ])->icon('heroicon-o-flag')
                            ->columns(4),
                        Tab::make(__('stallions.tabs.contact'))
                            ->schema([
                                TextInput::make('stud_en')->label(__('stallions.stud_en'))->placeholder('e.g. Muscat Stud')->maxLength(255),
                                TextInput::make('stud_ar')->label(__('stallions.stud_ar'))->placeholder('مثال: مربط مسقط')->maxLength(255)->extraInputAttributes($rtl),
                                Select::make('city_id')->label(__('stallions.city'))->relationship('city', LocationFields::nameColumn())->searchable()->preload(),
                                TextInput::make('contact_number')->label(__('stallions.contact_number'))->placeholder('e.g. 9123 4567')->tel()->maxLength(32),
                            ])->icon('heroicon-o-phone')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = LocationFields::nameColumn();

        return $table
            ->columns([
                ImageColumn::make('cover_photo')->label(__('stallions.photo'))->disk('public')->circular(),
                TextColumn::make($name)->label(__('stallions.name'))->searchable(['en_name', 'ar_name'])->sortable()
                    ->description(fn (Stallion $record): string => trim($record->sire.' × '.$record->dam, ' ×')),
                TextColumn::make('colour')->label(__('stallions.colour'))->formatStateUsing(fn (?string $state) => Stallion::colourOptions()[$state] ?? $state)->toggleable(),
                TextColumn::make('foaled_year')->label(__('stallions.foaled_year'))->sortable()->toggleable(),
                TextColumn::make('wins')->label(__('stallions.wins'))->sortable()->toggleable(),
                TextColumn::make('city.'.$name)->label(__('stallions.city'))->toggleable(),
                TextColumn::make('contact_number')->label(__('stallions.contact_number'))->searchable(),
                TextColumn::make('views_count')->label(__('admin_widgets.listing_views.views'))->icon('heroicon-o-eye')->numeric()->sortable()->toggleable(),
                ToggleColumn::make('is_active')->label(__('stallions.is_active')),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('colour')->label(__('stallions.colour'))->options(fn () => Stallion::colourOptions()),
                TernaryFilter::make('is_active')->label(__('stallions.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('page')->label(__('stallions.view_details'))->icon('heroicon-o-globe-alt')
                        ->url(fn (Stallion $record): string => route('stallions.show', $record))->openUrlInNewTab(),
                    Action::make('racingProfile')->label(__('stallions.open_racing_profile'))->icon('heroicon-o-flag')
                        ->url(fn (Stallion $record): string => route('racing.profile', ['entity' => 'horse', 'id' => $record->racing_horse_id]))
                        ->openUrlInNewTab()
                        ->visible(fn (Stallion $record): bool => $record->racing_horse_id !== null),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStallions::route('/'),
            'create' => CreateStallion::route('/create'),
            'edit' => EditStallion::route('/{record}/edit'),
        ];
    }
}
