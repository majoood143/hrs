<?php

namespace App\Filament\Resources\CmsMenuResource\RelationManagers;

use App\Filament\Support\TranslatableInput;
use App\Models\CmsMenuItem;
use App\Models\CmsPage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsMenuItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'allItems';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("label.{$code}")
                    ->label(__('cms_menu_item.fields.label') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale())
                    ->maxLength(255))
                    ->columnSpanFull(),

                Select::make('parent_id')
                    ->label(__('cms_menu_item.fields.parent'))
                    ->options(fn () => $this->getOwnerRecord()->allItems()->get()
                        ->mapWithKeys(fn (CmsMenuItem $item) => [$item->id => $item->getTranslation('label', app()->getLocale())]))
                    ->searchable()
                    ->native(false),

                Select::make('page_id')
                    ->label(__('cms_menu_item.fields.page'))
                    ->options(fn () => CmsPage::query()->get()->mapWithKeys(fn (CmsPage $page) => [$page->id => $page->getTranslation('title', app()->getLocale())]))
                    ->searchable()
                    ->native(false)
                    ->live(),

                TextInput::make('url')
                    ->label(__('cms_menu_item.fields.url'))
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => blank($get('page_id'))),

                Select::make('target')
                    ->label(__('cms_menu_item.fields.target'))
                    ->options([
                        '_self' => __('cms_menu_item.fields.target_self'),
                        '_blank' => __('cms_menu_item.fields.target_blank'),
                    ])
                    ->default('_self')
                    ->native(false),

                TextInput::make('order')
                    ->label(__('cms_menu_item.fields.order'))
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')
                    ->label(__('cms_menu_item.columns.label'))
                    ->weight('bold'),

                TextColumn::make('resolvedUrl')
                    ->label(__('cms_menu_item.fields.url'))
                    ->state(fn ($record) => $record->resolvedUrl()),

                TextColumn::make('target')
                    ->label(__('cms_menu_item.columns.target'))
                    ->badge(),

                TextColumn::make('order')
                    ->label(__('cms_menu_item.columns.order'))
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
