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
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
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
                    ->helperText(__('cms_menu_item.fields.parent_helper'))
                    ->options(fn (?CmsMenuItem $record) => $this->getOwnerRecord()->items()
                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                        ->get()
                        ->mapWithKeys(fn (CmsMenuItem $item) => [$item->id => $item->getTranslation('label', app()->getLocale())]))
                    ->searchable()
                    ->native(false),

                Select::make('page_id')
                    ->label(__('cms_menu_item.fields.page'))
                    ->options(fn () => CmsPage::query()->get()->mapWithKeys(fn (CmsPage $page) => [$page->id => $page->getTranslation('title', app()->getLocale())]))
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => blank($state) ?: $set('route_name', null)),

                Select::make('route_name')
                    ->label(__('cms_menu_item.fields.site_section'))
                    ->options(fn () => CmsMenuItem::siteSectionOptions())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->visible(fn ($get) => blank($get('page_id')))
                    ->afterStateUpdated(fn ($state, $set) => blank($state) ?: $set('url', null)),

                TextInput::make('url')
                    ->label(__('cms_menu_item.fields.url'))
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => blank($get('page_id')) && blank($get('route_name'))),

                Toggle::make('is_button')
                    ->label(__('cms_menu_item.fields.is_button'))
                    ->helperText(__('cms_menu_item.fields.is_button_helper')),

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
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, CmsMenuItem $record) => $record->parent_id ? '— ' . $state : $state),

                TextColumn::make('parent.label')
                    ->label(__('cms_menu_item.columns.parent'))
                    ->placeholder('—'),

                TextColumn::make('resolvedUrl')
                    ->label(__('cms_menu_item.fields.url'))
                    ->state(fn ($record) => $record->resolvedUrl()),

                TextColumn::make('target')
                    ->label(__('cms_menu_item.columns.target'))
                    ->badge(),

                IconColumn::make('is_button')
                    ->label(__('cms_menu_item.fields.is_button'))
                    ->boolean(),

                TextColumn::make('order')
                    ->label(__('cms_menu_item.columns.order'))
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw('COALESCE(parent_id, id) asc, parent_id is not null asc, `order` asc'))
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
