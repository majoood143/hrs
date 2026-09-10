<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsTagResource\Pages\CreateCmsTag;
use App\Filament\Resources\CmsTagResource\Pages\EditCmsTag;
use App\Filament\Resources\CmsTagResource\Pages\ListCmsTags;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Tags\Tag;

class CmsTagResource extends Resource
{
    protected static ?string $model = Tag::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 83;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_tag.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_tag.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_tag.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('cms_tag.sections.information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name.en')
                                    ->label(__('cms_tag.fields.name_en'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('name.ar')
                                    ->label(__('cms_tag.fields.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cms_tag.columns.name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name->en', 'name->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label(__('cms_tag.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
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
                    ->label(__('cms_tag.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_tag.empty_state.heading'))
            ->emptyStateDescription(__('cms_tag.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsTags::route('/'),
            'create' => CreateCmsTag::route('/create'),
            'edit' => EditCmsTag::route('/{record}/edit'),
        ];
    }
}
