<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentReportResource\Pages\ListContentReports;
use App\Models\ContentReport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Posts reported from the app (POST /api/v1/reports). Apple expects reports acted on within 24
 * hours: open the post, hide it there if it breaks the rules, then mark the report resolved.
 */
class ContentReportResource extends Resource
{
    protected static ?string $model = ContentReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'mobile-app/reports';

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.mobile_app');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_mobile_app.reports.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_mobile_app.reports.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_mobile_app.reports.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ContentReport::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin_mobile_app.reports.reported_at'))
                    ->dateTime(timezone: (string) config('stable_bookings.timezone', 'Asia/Muscat'))
                    ->sortable(),
                TextColumn::make('section')
                    ->label(__('admin_mobile_app.reports.section'))
                    ->formatStateUsing(fn (ContentReport $record) => __('admin_mobile_app.reports.sections.'.$record->section).' #'.$record->post_id)
                    ->url(fn (ContentReport $record) => $record->adminUrl(), shouldOpenInNewTab: true),
                TextColumn::make('reason')
                    ->label(__('admin_mobile_app.reports.reason'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.reports.reasons.'.$state)),
                TextColumn::make('details')
                    ->label(__('admin_mobile_app.reports.details'))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(200),
                TextColumn::make('status')
                    ->label(__('admin_mobile_app.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.reports.statuses.'.$state))
                    ->color(fn (string $state) => $state === ContentReport::STATUS_OPEN ? 'danger' : 'success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_mobile_app.fields.status'))
                    ->options([
                        ContentReport::STATUS_OPEN => __('admin_mobile_app.reports.statuses.open'),
                        ContentReport::STATUS_RESOLVED => __('admin_mobile_app.reports.statuses.resolved'),
                    ])
                    ->default(ContentReport::STATUS_OPEN),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label(__('admin_mobile_app.reports.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContentReport $record) => $record->status === ContentReport::STATUS_OPEN && Gate::allows('update', $record))
                    ->action(fn (ContentReport $record) => $record->update(['status' => ContentReport::STATUS_RESOLVED])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentReports::route('/'),
        ];
    }
}
