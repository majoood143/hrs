<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\CrmSegmentResource;
use App\Models\AppEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The profile's timeline: what they did in the app (and what the server recorded for them), newest first. Read-only. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_crm.profile.timeline');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label(__('admin_crm.events_table.when'))->dateTime('M d, Y H:i:s')->sortable(),
                TextColumn::make('name')
                    ->label(__('admin_crm.events_table.event'))
                    ->formatStateUsing(fn (string $state) => __('admin_crm.events.'.$state))
                    ->badge()
                    ->color(fn (string $state) => in_array($state, AppEvent::SERVER, true) ? 'success' : 'gray'),
                TextColumn::make('screen')->label(__('admin_crm.events_table.screen'))->placeholder('—')->fontFamily('mono'),
                TextColumn::make('properties')
                    ->label(__('admin_crm.events_table.details'))
                    ->getStateUsing(fn (AppEvent $record) => collect($record->properties ?? [])->map(fn ($v, $k) => $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v))->implode(' · '))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('platform')
                    ->label(__('admin_crm.events_table.app'))
                    ->getStateUsing(fn (AppEvent $record) => trim(($record->platform ?? '').' '.($record->app_version ?? '')) ?: null)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('name')->label(__('admin_crm.events_table.event'))->options(fn () => CrmSegmentResource::eventOptions())->multiple(),
            ]);
    }
}
