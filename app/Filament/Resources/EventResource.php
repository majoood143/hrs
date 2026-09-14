<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Models\Event;
use App\Models\EventCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 82;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('events.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('events.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('events.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('events.sections.details'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title.en')
                                ->label(__('events.fields.title_en'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('title.ar')
                                ->label(__('events.fields.title_ar'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),

                        Grid::make(3)->schema([
                            DatePicker::make('date')
                                ->label(__('events.fields.date'))
                                ->required()
                                ->native(false),

                            TimePicker::make('start_time')
                                ->label(__('events.fields.start_time'))
                                ->seconds(false)
                                ->required(),

                            TimePicker::make('end_time')
                                ->label(__('events.fields.end_time'))
                                ->seconds(false)
                                ->required()
                                ->rules([
                                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $start = $get('start_time');
                                        if ($start && $value && $value <= $start) {
                                            $fail(__('events.validation.end_after_start'));
                                        }
                                    },
                                ]),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->label(__('events.fields.category'))
                                ->relationship('category', 'en_name', modifyQueryUsing: fn ($query) => $query->orderBy('order'))
                                ->getOptionLabelFromRecordUsing(fn (EventCategory $record) => $record->name)
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('en_name')
                                        ->label(__('event_categories.fields.en_name'))
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('ar_name')
                                        ->label(__('event_categories.fields.ar_name'))
                                        ->required()
                                        ->maxLength(255)
                                        ->extraInputAttributes(['dir' => 'rtl']),

                                    ColorPicker::make('color')
                                        ->label(__('event_categories.fields.color'))
                                        ->required()
                                        ->default('#5c6259'),
                                ]),

                            TextInput::make('link')
                                ->label(__('events.fields.link'))
                                ->helperText(__('events.fields.link_helper'))
                                ->maxLength(255)
                                ->rules([
                                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                        if (filled($value) && ! preg_match('#^(/|https?://)#i', (string) $value)) {
                                            $fail(__('events.validation.link_format'));
                                        }
                                    },
                                ]),
                        ]),
                    ]),

                Section::make(__('events.sections.description'))
                    ->schema([
                        Grid::make(2)->schema([
                            RichEditor::make('description.en')
                                ->label(__('events.fields.description_en')),

                            RichEditor::make('description.ar')
                                ->label(__('events.fields.description_ar'))
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('events.columns.title'))
                    ->getStateUsing(fn (Event $record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(['title->en', 'title->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('date')
                    ->label(__('events.columns.date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('time')
                    ->label(__('events.columns.time'))
                    ->getStateUsing(fn (Event $record) => \Illuminate\Support\Carbon::parse($record->start_time)->format('g:i A')
                        .' - '.\Illuminate\Support\Carbon::parse($record->end_time)->format('g:i A')),

                TextColumn::make('category')
                    ->label(__('events.columns.category'))
                    ->badge()
                    ->formatStateUsing(fn (Event $record) => $record->categoryLabel())
                    ->color(fn (Event $record) => \Filament\Support\Colors\Color::hex($record->categoryColor())),

                TextColumn::make('link')
                    ->label(__('events.columns.link'))
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'asc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('events.filters.category'))
                    ->relationship('category', 'en_name')
                    ->getOptionLabelFromRecordUsing(fn (EventCategory $record) => $record->name),
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
                    ->label(__('events.actions.create_first')),
            ])
            ->emptyStateHeading(__('events.empty_state.heading'))
            ->emptyStateDescription(__('events.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
