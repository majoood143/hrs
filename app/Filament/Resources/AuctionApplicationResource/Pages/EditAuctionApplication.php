<?php

namespace App\Filament\Resources\AuctionApplicationResource\Pages;

use App\Filament\Resources\AuctionApplicationResource;
use App\Filament\Resources\AuctionLotResource;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Services\Auctions\Applications;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Unique;

class EditAuctionApplication extends EditRecord
{
    protected static string $resource = AuctionApplicationResource::class;

    protected function getHeaderActions(): array
    {
        $pending = fn () => $this->getRecord()->status === 'pending' && auth()->user()?->can('update', $this->getRecord());

        return [
            Action::make('approve')
                ->label(__('auctions.admin.actions.approve_application'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($pending)
                ->modalDescription(__('auctions.admin.approve_application_help'))
                ->schema([
                    Select::make('auction_id')->label(__('auctions.admin.auction'))
                        ->options(fn () => Auction::query()->whereIn('status', ['draft', 'published'])->orderByDesc('bidding_starts_at')->get()
                            ->mapWithKeys(fn (Auction $a) => [$a->id => $a->title])->all())
                        ->required()->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('lot_number', (int) AuctionLot::query()->where('auction_id', $state)->max('lot_number') + 1)),
                    TextInput::make('lot_number')->label(__('auctions.admin.fields.lot_number'))->placeholder('e.g. 12')->integer()->minValue(1)->required()
                        ->rules([fn (Get $get) => (new Unique('auction_lots', 'lot_number'))->where('auction_id', $get('auction_id'))]),
                    TextInput::make('starting_price')->label(__('auctions.admin.fields.starting_price'))->placeholder('e.g. 1000')->numeric()->minValue(1)->required(),
                    TextInput::make('reserve_price')->label(__('auctions.admin.fields.reserve_price'))->placeholder('e.g. 3000')->numeric()->minValue(0)
                        ->default(fn () => $this->getRecord()->asking_reserve),
                ])
                ->action(function (array $data) {
                    $lot = app(Applications::class)->approve(
                        $this->getRecord(),
                        Auction::query()->findOrFail($data['auction_id']),
                        (int) $data['lot_number'],
                        (string) $data['starting_price'],
                        filled($data['reserve_price'] ?? null) ? (string) $data['reserve_price'] : null,
                        auth()->user(),
                    );
                    Notification::make()->success()->title(__('auctions.admin.application_approved'))->send();
                    $this->redirect(AuctionLotResource::getUrl('edit', ['record' => $lot]));
                }),
            Action::make('reject')
                ->label(__('auctions.admin.actions.reject_application'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($pending)
                ->schema([
                    Textarea::make('reason')->label(__('auctions.admin.fields.reject_reason'))->placeholder(__('auctions.admin.reject_reason_placeholder'))->required()->maxLength(500),
                ])
                ->action(function (array $data) {
                    app(Applications::class)->reject($this->getRecord(), $data['reason'], auth()->user());
                    Notification::make()->success()->title(__('auctions.admin.application_rejected'))->send();
                    $this->refreshFormData(['status', 'reject_reason']);
                }),
        ];
    }

    /** Only a waiting application can be edited (typos fixed before approving). */
    protected function getFormActions(): array
    {
        return $this->getRecord()->status === 'pending' ? parent::getFormActions() : [];
    }
}
