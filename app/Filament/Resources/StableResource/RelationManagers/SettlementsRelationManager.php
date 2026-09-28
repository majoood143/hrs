<?php

namespace App\Filament\Resources\StableResource\RelationManagers;

use App\Filament\Pages\StableBalances;
use App\Filament\Pages\StableStatementReport;
use App\Models\Stable;
use App\Models\StableSettlement;
use App\Services\Reports\StableStatement;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** The payouts to this stable and receipts from it, with its balance; mistakes can be removed. */
class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stable_statement.sections.settlements');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function stable(): Stable
    {
        /** @var Stable $stable */
        $stable = $this->getOwnerRecord();

        return $stable;
    }

    public function table(Table $table): Table
    {
        $balance = StableStatement::balance($this->stable());

        return $table
            ->description(__('stable_statement.admin.balance').': '.Money::format(abs($balance)).' · '.__('stable_statement.admin.'.($balance > 0 ? 'we_owe' : ($balance < 0 ? 'they_owe' : 'settled'))))
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('paid_on')->label(__('stable_statement.fields.paid_on'))->date()->sortable(),
                TextColumn::make('direction')
                    ->label(__('stable_statement.fields.direction'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('stable_statement.directions.'.$state))
                    ->color(fn (string $state) => $state === StableSettlement::PAYOUT ? 'success' : 'info'),
                TextColumn::make('amount')->label(__('stable_statement.fields.amount'))->formatStateUsing(fn (StableSettlement $record) => Money::formatHtml($record->amountBaisa())),
                TextColumn::make('method')->label(__('stable_statement.fields.method'))->formatStateUsing(fn (string $state) => __('stable_statement.methods.'.$state)),
                TextColumn::make('reference')->label(__('stable_statement.fields.reference'))->placeholder('—'),
                TextColumn::make('recorder.name')->label(__('stable_statement.fields.recorded_by'))->placeholder('—'),
            ])
            ->headerActions([
                ...collect([StableSettlement::PAYOUT, StableSettlement::RECEIPT])->map(fn (string $direction) => Action::make('record_'.$direction)
                    ->label(__('stable_statement.admin.record_'.$direction))
                    ->icon($direction === StableSettlement::PAYOUT ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left')
                    ->modalDescription(__('stable_statement.admin.record_'.$direction.'_hint'))
                    ->visible(fn () => Gate::allows('update', $this->stable()))
                    ->schema(fn () => StableBalances::settlementForm($direction, $direction === StableSettlement::PAYOUT ? max(0, $balance) : max(0, -$balance)))
                    ->action(fn (array $data) => StableBalances::recordSettlement($this->stable(), $direction, $data)))->all(),
                Action::make('statement')
                    ->label(__('stable_statement.admin.statement'))
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn () => StableStatementReport::getUrl(['stable' => $this->stable()->getKey()])),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('stable_statement.admin.remove'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('stable_statement.admin.remove_hint'))
                    ->visible(fn () => Gate::allows('update', $this->stable()))
                    ->action(function (StableSettlement $record): void {
                        $record->delete();
                        Notification::make()->success()->title(__('stable_statement.admin.removed'))->send();
                    }),
            ])
            ->emptyStateHeading(__('stable_statement.no_settlements'));
    }
}
