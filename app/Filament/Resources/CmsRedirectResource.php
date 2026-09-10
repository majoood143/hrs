<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsRedirectResource\Pages\CreateCmsRedirect;
use App\Filament\Resources\CmsRedirectResource\Pages\EditCmsRedirect;
use App\Filament\Resources\CmsRedirectResource\Pages\ListCmsRedirects;
use App\Models\CmsRedirect;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsRedirectResource extends Resource
{
    protected static ?string $model = CmsRedirect::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-turn-right-up';
    protected static ?int $navigationSort = 85;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_redirect.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_redirect.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_redirect.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('cms_redirect.sections.information'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('from_path')
                                ->label(__('cms_redirect.fields.from_path'))
                                ->helperText(__('cms_redirect.fields.from_path_helper'))
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->dehydrateStateUsing(fn ($state) => ltrim((string) $state, '/')),

                            TextInput::make('to_path')
                                ->label(__('cms_redirect.fields.to_path'))
                                ->required()
                                ->maxLength(255),
                        ]),

                        Select::make('status_code')
                            ->label(__('cms_redirect.fields.status_code'))
                            ->options([
                                301 => __('cms_redirect.status_codes.301'),
                                302 => __('cms_redirect.status_codes.302'),
                            ])
                            ->default(301)
                            ->native(false)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')
                    ->label(__('cms_redirect.columns.from_path'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('to_path')
                    ->label(__('cms_redirect.columns.to_path'))
                    ->searchable(),

                TextColumn::make('status_code')
                    ->label(__('cms_redirect.columns.status_code'))
                    ->badge(),

                TextColumn::make('hits')
                    ->label(__('cms_redirect.columns.hits'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
                    ->label(__('cms_redirect.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_redirect.empty_state.heading'))
            ->emptyStateDescription(__('cms_redirect.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsRedirects::route('/'),
            'create' => CreateCmsRedirect::route('/create'),
            'edit' => EditCmsRedirect::route('/{record}/edit'),
        ];
    }
}
