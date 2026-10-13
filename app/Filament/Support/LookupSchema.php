<?php

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

/**
 * The admin form and table of a two-name lookup list (horse types, genders, colours, statuses,
 * authorities, pollinations, stable amenities). Deleting goes through `InUseDeleteActions`, so a
 * model using `RefusesDeleteWhileReferenced` explains why one in use stays.
 */
final class LookupSchema
{
    /** @param  array<int, mixed>  $extra  fields after the two names */
    public static function form(Schema $schema, array $extra = []): Schema
    {
        return $schema->components([
            TextInput::make('en_name')
                ->label(__('admin_lookups.fields.en_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('ar_name')
                ->label(__('admin_lookups.fields.ar_name'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'rtl']),
            ...$extra,
        ]);
    }

    /**
     * @param  array<int, mixed>  $columns  columns after the two names
     * @param  array<int, mixed>  $actions  row actions after view/edit/delete
     */
    public static function table(Table $table, array $columns = [], array $actions = []): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('admin_lookups.fields.en_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_lookups.fields.ar_name'))
                    ->searchable()
                    ->sortable(),
                ...$columns,
                TextColumn::make('created_at')
                    ->label(__('admin_lookups.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin_lookups.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    InUseDeleteActions::delete(),
                    ...$actions,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    InUseDeleteActions::bulk(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }
}
