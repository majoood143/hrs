<?php

namespace App\Filament\Resources\AuctionResource\RelationManagers;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\AuctionBidder;
use App\Models\Customer;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Settlement;
use App\Services\Orders\RefundException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Who may bid in this auction. Customers register on the website or app (phase 2); an admin can also
 * add one (e.g. a phone bidder) and approves, rejects or blocks them. Only approved bidders can bid.
 */
class BiddersRelationManager extends RelationManager
{
    protected static string $relationship = 'bidders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('auctions.admin.bidders');
    }

    public static function getModelLabel(): string
    {
        return __('auctions.admin.bidder');
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')
                ->label(__('auctions.admin.fields.customer'))
                ->placeholder(__('auctions.admin.customer_placeholder'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Customer::query()
                    ->where(fn ($q) => $q->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->limit(20)->get()->mapWithKeys(fn (Customer $c) => [$c->id => trim($c->name.' · '.$c->phone)])->all())
                ->getOptionLabelUsing(fn ($value) => ($c = Customer::find($value)) ? trim($c->name.' · '.$c->phone) : null)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('auction_id', $this->getOwnerRecord()->getKey()))
                ->required()
                ->disabledOn('edit'),
            Select::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => AuctionBidder::statusOptions())->default('approved')->required()->selectablePlaceholder(false),
            Textarea::make('note')->label(__('auctions.admin.fields.note'))->placeholder(__('auctions.admin.note_placeholder'))->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'depositOrder', 'auction']))
            ->defaultSort('paddle_number')
            ->columns([
                TextColumn::make('paddle_number')->label(__('auctions.admin.fields.paddle_number'))->sortable()->prefix('#'),
                TextColumn::make('customer.name')->label(__('auctions.admin.fields.customer'))->searchable()->placeholder('—'),
                TextColumn::make('customer.phone')->label(__('auctions.admin.fields.phone'))->searchable(),
                TextColumn::make('status')->label(__('auctions.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => AuctionBidder::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'blocked' => 'danger'][$state] ?? 'gray')
                    ->icon(fn (string $state) => ['approved' => 'heroicon-o-check-circle', 'pending' => 'heroicon-o-clock'][$state] ?? 'heroicon-o-no-symbol'),
                TextColumn::make('deposit_state')->label(__('auctions.admin.fields.deposit'))->badge()
                    ->state(fn (AuctionBidder $record) => app(Settlement::class)->depositState($record))
                    ->formatStateUsing(fn (string $state) => __('auctions.admin.deposit_states.'.$state))
                    ->color(fn (string $state) => ['held' => 'info', 'refund_due' => 'warning', 'refunded' => 'success', 'kept' => 'danger'][$state] ?? 'gray')
                    ->url(fn (AuctionBidder $record) => $record->deposit_order_id ? ServiceOrderResource::getUrl('view', ['record' => $record->deposit_order_id]) : null),
                TextColumn::make('bids_count')->label(__('auctions.admin.fields.bids'))->counts(['bids' => fn ($q) => $q->visible()]),
                TextColumn::make('approved_at')->label(__('auctions.admin.fields.approved_at'))->dateTime('j M Y, H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => AuctionBidder::statusOptions()),
            ])
            ->headerActions([
                CreateAction::make()->icon('heroicon-o-user-plus')
                    ->mutateDataUsing(fn (array $data) => $data + ['approved_at' => ($data['status'] ?? null) === 'approved' ? now() : null]),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::statusAction('approve', 'approved', 'heroicon-o-check-circle', 'success'),
                    self::statusAction('reject', 'rejected', 'heroicon-o-x-circle', 'danger'),
                    self::statusAction('block', 'blocked', 'heroicon-o-no-symbol', 'danger'),
                    Action::make('refundDeposit')->label(__('auctions.admin.actions.refund_deposit'))->icon('heroicon-o-arrow-uturn-left')->color('warning')
                        ->requiresConfirmation()->modalDescription(__('auctions.admin.refund_deposit_help'))
                        ->visible(fn (AuctionBidder $record, RelationManager $livewire) => ! $livewire->isReadOnly() && app(Settlement::class)->depositState($record) === 'refund_due')
                        ->action(function (AuctionBidder $record) {
                            try {
                                app(Settlement::class)->refundDeposit($record, auth()->user());
                                Notification::make()->success()->title(__('auctions.admin.deposit_refunded'))->send();
                            } catch (AuctionBidException|RefundException $e) {
                                Notification::make()->danger()->title($e->getMessage())->send();
                            }
                        }),
                    Action::make('keepDeposit')->label(__('auctions.admin.actions.keep_deposit'))->icon('heroicon-o-lock-closed')->color('danger')
                        ->requiresConfirmation()->modalDescription(__('auctions.admin.keep_deposit_help'))
                        ->visible(fn (AuctionBidder $record, RelationManager $livewire) => ! $livewire->isReadOnly() && in_array(app(Settlement::class)->depositState($record), ['held', 'refund_due'], true))
                        ->action(fn (AuctionBidder $record) => $record->forceFill(['deposit_kept_at' => now()])->save()),
                    EditAction::make(),
                ]),
            ]);
    }

    private static function statusAction(string $name, string $status, string $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__('auctions.admin.actions.'.$name))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (AuctionBidder $record, RelationManager $livewire) => $record->status !== $status && ! $livewire->isReadOnly())
            ->action(fn (AuctionBidder $record) => $record->update([
                'status' => $status,
                'approved_at' => $status === 'approved' ? now() : $record->approved_at,
            ]));
    }
}
