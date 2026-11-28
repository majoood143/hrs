<?php

namespace App\Filament\Resources\AuctionLotResource\Pages;

use App\Filament\Resources\AuctionLotResource;
use App\Filament\Resources\AuctionResource;
use App\Jobs\SendAuctionMessage;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\SiteSetting;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\Money;
use App\Services\Auctions\Settlement;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditAuctionLot extends EditRecord
{
    protected static string $resource = AuctionLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('auction')->label(__('auctions.admin.open_auction'))->icon('heroicon-o-megaphone')->color('gray')
                ->url(fn (AuctionLot $record) => AuctionResource::getUrl('edit', ['record' => $record->auction_id])),
            $this->phoneBidAction(),
            ActionGroup::make([
                $this->bankTransferAction(),
                $this->offerToNextAction(),
                $this->handedOverAction(),
                $this->sellerPaidAction(),
            ])->label(__('auctions.admin.settlement'))->icon('heroicon-o-receipt-percent')->button()->color('gray')
                ->visible(fn () => $this->getRecord()->status === 'sold' && auth()->user()?->can('update', $this->getRecord())),
            DeleteAction::make()->hidden(fn (AuctionLot $record) => $record->bids_count > 0),
        ];
    }

    private function bankTransferAction(): Action
    {
        return Action::make('bankTransfer')
            ->label(__('auctions.admin.actions.bank_transfer'))
            ->icon('heroicon-o-building-library')
            ->visible(fn (AuctionLot $record) => (bool) $record->invoice?->isPayable())
            ->modalDescription(fn (AuctionLot $record) => __('auctions.admin.bank_transfer_help', ['total' => SiteSetting::formatCurrency($record->invoice?->total)]))
            ->schema([
                TextInput::make('reference')->label(__('auctions.admin.fields.transfer_reference'))->placeholder('e.g. FT2611240012345')->required()->maxLength(120),
            ])
            ->action(function (AuctionLot $record, array $data) {
                app(Settlement::class)->recordBankTransfer($record->invoice, $data['reference']);
                Notification::make()->success()->title(__('auctions.admin.invoice_paid'))->send();
            });
    }

    private function offerToNextAction(): Action
    {
        return Action::make('offerToNext')
            ->label(__('auctions.admin.actions.offer_to_next'))
            ->icon('heroicon-o-arrow-uturn-down')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('auctions.admin.offer_to_next_help'))
            ->visible(fn (AuctionLot $record) => (bool) $record->invoice?->isPayable())
            ->action(function (AuctionLot $record) {
                $order = app(Settlement::class)->offerToNext($record, auth()->user());
                Notification::make()->success()->title($order ? __('auctions.admin.offered') : __('auctions.admin.no_next_bidder'))->send();
                $this->redirect(AuctionLotResource::getUrl('edit', ['record' => $record]));
            });
    }

    private function handedOverAction(): Action
    {
        return Action::make('handedOver')
            ->label(__('auctions.admin.actions.handed_over'))
            ->icon('heroicon-o-truck')
            ->requiresConfirmation()
            ->visible(fn (AuctionLot $record) => $record->handed_over_at === null && (bool) $record->invoice?->isPaid())
            ->action(fn (AuctionLot $record) => $record->forceFill(['handed_over_at' => now()])->save());
    }

    private function sellerPaidAction(): Action
    {
        return Action::make('sellerPaid')
            ->label(__('auctions.admin.actions.seller_paid'))
            ->icon('heroicon-o-banknotes')
            ->visible(fn (AuctionLot $record) => $record->payout_paid_at === null && (bool) $record->invoice?->isPaid())
            ->modalDescription(fn (AuctionLot $record) => __('auctions.admin.seller_paid_help', ['amount' => SiteSetting::formatCurrency($record->payout_amount)]))
            ->schema([
                TextInput::make('payout_reference')->label(__('auctions.admin.fields.payout_reference'))->placeholder('e.g. FT2611240098765')->required()->maxLength(120),
            ])
            ->action(function (AuctionLot $record, array $data) {
                $record->forceFill(['payout_paid_at' => now(), 'payout_reference' => $data['payout_reference']])->save();
                if ($record->seller_customer_id) {
                    SendAuctionMessage::dispatch(SendAuctionMessage::SELLER_PAID, $record->id);
                }
            });
    }

    /** A bid for an approved bidder who called in; the same rules as a bid from the website, logged with the admin. */
    private function phoneBidAction(): Action
    {
        return Action::make('phoneBid')
            ->label(__('auctions.admin.actions.phone_bid'))
            ->icon('heroicon-o-phone-arrow-down-left')
            ->visible(fn (AuctionLot $record) => $record->isOpenAt(now()) && auth()->user()?->can('update', $record))
            ->modalDescription(fn (AuctionLot $record) => __('auctions.admin.phone_bid_help', ['minimum' => Money::omr($record->minimumNextBid())]))
            ->schema([
                Select::make('bidder_id')->label(__('auctions.admin.bidder'))
                    ->options(fn (AuctionLot $record) => AuctionBidder::query()->with('customer')
                        ->where('auction_id', $record->auction_id)->where('status', 'approved')->orderBy('paddle_number')->get()
                        ->mapWithKeys(fn (AuctionBidder $b) => [$b->id => '#'.$b->paddle_number.' · '.trim(($b->customer?->name ?? '').' '.($b->customer?->phone ?? ''))])->all())
                    ->searchable()->required(),
                TextInput::make('amount')->label(__('auctions.admin.fields.amount'))->placeholder('e.g. 1500')->numeric()->minValue(0.001)->required(),
                TextInput::make('max_amount')->label(__('auctions.admin.fields.max_amount'))->placeholder('e.g. 3000')->helperText(__('auctions.admin.max_amount_help'))->numeric()->minValue(0.001),
            ])
            ->action(function (array $data, AuctionLot $record) {
                try {
                    app(Bidding::class)->place(
                        $record,
                        AuctionBidder::query()->findOrFail($data['bidder_id']),
                        Money::baisa($data['amount']),
                        filled($data['max_amount'] ?? null) ? Money::baisa($data['max_amount']) : null,
                        auth()->user(),
                    );
                } catch (AuctionBidException $e) {
                    Notification::make()->danger()->title(__('auctions.admin.bid_refused'))->body($e->getMessage())->send();

                    throw new Halt;
                }

                Notification::make()->success()->title(__('auctions.admin.bid_placed'))->send();
                $this->refreshFormData(['current_price', 'ends_at', 'starting_price', 'auction_id', 'lot_number']);
            });
    }
}
