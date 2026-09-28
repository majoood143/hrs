<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * A stable's change log as a table: who changed what and when, with the admins' changes on the
 * stable's behalf marked. Shared by the stable's admin page and the owner's own log.
 */
final class StableChangeLogTable
{
    /** @return Builder<Activity> */
    public static function query(int $stableId): Builder
    {
        return Activity::query()
            ->with('causer')
            ->where('log_name', 'stable')
            ->where('properties->stable_id', $stableId);
    }

    public static function configure(Table $table, int $stableId): Table
    {
        return $table
            ->query(fn () => self::query($stableId))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('stable_panel.log.when'))
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Activity $record) => $record->created_at?->diffForHumans()),
                TextColumn::make('causer.name')
                    ->label(__('stable_panel.log.who'))
                    ->placeholder(__('stable_panel.log.system'))
                    ->description(fn (Activity $record) => $record->getExtraProperty('as_admin') ? __('stable_panel.log.as_admin') : null)
                    ->icon(fn (Activity $record) => $record->getExtraProperty('as_admin') ? 'heroicon-o-shield-exclamation' : 'heroicon-o-user')
                    ->iconColor(fn (Activity $record) => $record->getExtraProperty('as_admin') ? 'warning' : 'gray'),
                TextColumn::make('event')
                    ->label(__('stable_panel.log.what'))
                    ->state(fn (Activity $record) => self::what($record))
                    ->badge()
                    ->color(fn (Activity $record) => match ($record->event) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'admin_opened' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('changes')
                    ->label(__('stable_panel.log.changes'))
                    ->state(fn (Activity $record) => self::changes($record))
                    ->html()
                    ->wrap(),
            ])
            ->filters([
                TernaryFilter::make('as_admin')
                    ->label(__('stable_panel.log.admin_only'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('properties->as_admin', true),
                        false: fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('properties->as_admin', false)->orWhereNull('properties->as_admin')),
                    ),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading(__('stable_panel.log.empty'));
    }

    /** "Service updated", "Opened the stable panel"… */
    public static function what(Activity $activity): string
    {
        if ($activity->event === 'admin_opened') {
            return __('stable_panel.log.opened');
        }

        $subject = __('stable_panel.log.subjects.'.class_basename((string) $activity->subject_type));

        return __('stable_panel.log.events.'.($activity->event ?: 'updated'), ['subject' => $subject]);
    }

    /** "price: 15.000 → 18.000" per changed field (long values shortened). */
    public static function changes(Activity $activity): ?HtmlString
    {
        $new = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);

        if ($new === [] && $old === []) {
            return null;
        }

        $show = fn ($value): string => e(mb_strimwidth(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? '✓' : '✗') : (string) ($value ?? '—')), 0, 60, '…'));

        $lines = collect(array_unique([...array_keys($new), ...array_keys($old)]))
            ->take(8)
            ->map(fn (string $field) => '<span class="font-medium">'.e(str_replace('_', ' ', $field)).'</span>: '
                .(array_key_exists($field, $old) ? $show($old[$field]).' → ' : '').$show($new[$field] ?? null));

        return new HtmlString($lines->implode('<br>'));
    }
}
