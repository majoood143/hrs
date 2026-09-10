<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuccessStoryResource\Pages\CreateSuccessStory;
use App\Filament\Resources\SuccessStoryResource\Pages\EditSuccessStory;
use App\Filament\Resources\SuccessStoryResource\Pages\ListSuccessStories;
use App\Models\SuccessStory;
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
use Filament\Tables\Table;

class SuccessStoryResource extends Resource
{
    protected static ?string $model = SuccessStory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';
    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('success_story.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('success_story.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('success_story.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('success_story.sections.information'))
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('horse_id')
                                ->label(__('success_story.fields.horse'))
                                ->relationship('horse', 'en_name')
                                ->searchable()
                                ->preload()
                                ->native(false),

                            TextInput::make('owner_name')
                                ->label(__('success_story.fields.owner_name'))
                                ->required()
                                ->maxLength(255),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('en_route')
                                ->label(__('success_story.fields.route_en'))
                                ->helperText(__('success_story.fields.route_en_helper'))
                                ->maxLength(255),

                            TextInput::make('ar_route')
                                ->label(__('success_story.fields.route_ar'))
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),

                        Grid::make(2)->schema([
                            Textarea::make('en_quote')
                                ->label(__('success_story.fields.quote_en'))
                                ->required()
                                ->rows(3),

                            Textarea::make('ar_quote')
                                ->label(__('success_story.fields.quote_ar'))
                                ->required()
                                ->rows(3)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),

                        FileUpload::make('photo')
                            ->label(__('success_story.fields.photo'))
                            ->image()
                            ->disk('public')
                            ->directory('success-stories'),

                        Grid::make(2)->schema([
                            Toggle::make('is_published')
                                ->label(__('success_story.fields.is_published'))
                                ->default(true),

                            TextInput::make('order')
                                ->label(__('success_story.fields.order'))
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
                ImageColumn::make('photo')
                    ->label(__('success_story.columns.photo'))
                    ->disk('public')
                    ->circular(),

                TextColumn::make('owner_name')
                    ->label(__('success_story.columns.owner_name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('en_route')
                    ->label(__('success_story.columns.route')),

                IconColumn::make('is_published')
                    ->label(__('success_story.columns.is_published'))
                    ->boolean(),

                TextColumn::make('order')
                    ->label(__('success_story.columns.order'))
                    ->sortable(),
            ])
            ->defaultSort('order')
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
                    ->label(__('success_story.actions.create_first')),
            ])
            ->emptyStateHeading(__('success_story.empty_state.heading'))
            ->emptyStateDescription(__('success_story.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuccessStories::route('/'),
            'create' => CreateSuccessStory::route('/create'),
            'edit' => EditSuccessStory::route('/{record}/edit'),
        ];
    }
}
