<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionLotResource\Pages\CreateAuctionLot;
use App\Filament\Resources\AuctionLotResource\Pages\EditAuctionLot;
use App\Filament\Resources\AuctionLotResource\Pages\ListAuctionLots;
use App\Filament\Resources\AuctionLotResource\RelationManagers\BidsRelationManager;
use App\Filament\Support\PlaceFormFields;
use App\Models\AuctionLot;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\Stallion;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Admin → Auctions → Lots: one horse in a sale, its catalogue page and its bids. */
class AuctionLotResource extends Resource
{
    protected static ?string $model = AuctionLot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.auctions');
    }

    public static function getModelLabel(): string
    {
        return __('auctions.admin.lot');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auctions.admin.lots');
    }

    public static function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl'];
        $hasBids = fn (?AuctionLot $record) => (int) $record?->bids_count > 0;

        return $schema->components([
            Tabs::make('Lot')->columnSpanFull()->tabs([
                Tab::make(__('auctions.admin.tabs.horse'))->icon('heroicon-o-information-circle')->columns(2)->schema([
                    Select::make('auction_id')->label(__('auctions.admin.auction'))
                        ->relationship('auction', app()->getLocale() === 'ar' ? 'ar_title' : 'en_title')
                        ->default(fn () => request()->integer('auction') ?: null)
                        ->required()->disabled($hasBids)->dehydrated()->live(),
                    TextInput::make('lot_number')->label(__('auctions.admin.fields.lot_number'))->placeholder('e.g. 12')->helperText(__('auctions.admin.lot_number_help'))
                        ->integer()->minValue(1)->required()->disabled($hasBids)->dehydrated()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('auction_id', $get('auction_id'))),
                    TextInput::make('en_name')->label(__('auctions.admin.fields.en_name'))->placeholder('e.g. Bahar Muscat')->required()->maxLength(255),
                    TextInput::make('ar_name')->label(__('auctions.admin.fields.ar_name'))->placeholder('مثال: بحر مسقط')->required()->maxLength(255)->extraInputAttributes($rtl),
                    TextInput::make('en_tagline')->label(__('auctions.admin.fields.en_tagline'))->placeholder('e.g. Talented son of a Derby winner')->maxLength(255),
                    TextInput::make('ar_tagline')->label(__('auctions.admin.fields.ar_tagline'))->placeholder('مثال: ابن موهوب لبطل الداربي')->maxLength(255)->extraInputAttributes($rtl),
                    Select::make('gender')->label(__('auctions.admin.fields.gender'))->options(fn () => AuctionLot::genderOptions()),
                    DatePicker::make('born_on')->label(__('auctions.admin.fields.born_on'))->placeholder('2023-03-14')->native(false)->maxDate(now()),
                    Select::make('colour')->label(__('auctions.admin.fields.colour'))->options(fn () => Stallion::colourOptions()),
                    TextInput::make('breed')->label(__('auctions.admin.fields.breed'))->placeholder('e.g. Purebred Arabian')->maxLength(255),
                    TextInput::make('height_cm')->label(__('auctions.admin.fields.height_cm'))->placeholder('e.g. 152')->integer()->minValue(50)->maxValue(220)->suffix('cm'),
                    TextInput::make('genetic_tests')->label(__('auctions.admin.fields.genetic_tests'))->placeholder('e.g. SCID N/N, CA N/N')->maxLength(255),
                    TextInput::make('ueln')->label(__('auctions.admin.fields.ueln'))->placeholder('e.g. 512001202300123')->maxLength(64),
                    TextInput::make('microchip')->label(__('auctions.admin.fields.microchip'))->placeholder('e.g. 512098100012345')->maxLength(64),
                    TextInput::make('racing_horse_id')->label(__('auctions.admin.fields.racing_horse_id'))->placeholder('e.g. 12345')->helperText(__('stallions.racing_horse_id_help'))->integer()->minValue(1),
                ]),
                Tab::make(__('auctions.admin.tabs.people'))->icon('heroicon-o-users')->columns(2)->schema([
                    TextInput::make('owner_name')->label(__('auctions.admin.fields.owner_name'))->placeholder('e.g. Salim Al Harthy')->maxLength(255),
                    TextInput::make('owner_town')->label(__('auctions.admin.fields.owner_town'))->placeholder('e.g. Seeb')->maxLength(255),
                    TextInput::make('breeder_name')->label(__('auctions.admin.fields.breeder_name'))->placeholder('e.g. Muscat Stud')->maxLength(255),
                    TextInput::make('breeder_town')->label(__('auctions.admin.fields.breeder_town'))->placeholder('e.g. Barka')->maxLength(255),
                    TextInput::make('clinic')->label(__('auctions.admin.fields.clinic'))->placeholder('e.g. Royal Equine Clinic')->maxLength(255),
                    Select::make('seller_customer_id')->label(__('auctions.admin.fields.seller'))->helperText(__('auctions.admin.seller_help'))
                        ->placeholder(__('auctions.admin.customer_placeholder'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Customer::query()
                            ->where(fn ($q) => $q->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                            ->limit(20)->get()->mapWithKeys(fn (Customer $c) => [$c->id => trim($c->name.' · '.$c->phone)])->all())
                        ->getOptionLabelUsing(fn ($value) => ($c = Customer::find($value)) ? trim($c->name.' · '.$c->phone) : null),
                ]),
                Tab::make(__('auctions.admin.tabs.pedigree'))->icon('heroicon-o-share')->columns(2)->schema([
                    TextInput::make('sire_en')->label(__('stallions.sire_en'))->placeholder('e.g. Amer (SA)')->maxLength(255),
                    TextInput::make('sire_ar')->label(__('stallions.sire_ar'))->placeholder('مثال: عامر')->maxLength(255)->extraInputAttributes($rtl),
                    TextInput::make('dam_en')->label(__('stallions.dam_en'))->placeholder('e.g. Nisaee (FR)')->maxLength(255),
                    TextInput::make('dam_ar')->label(__('stallions.dam_ar'))->placeholder('مثال: نسائي')->maxLength(255)->extraInputAttributes($rtl),
                    ...collect(AuctionLot::PEDIGREE_KEYS)->map(fn (string $key) => TextInput::make("pedigree.{$key}")
                        ->label(self::ancestorLabel($key))
                        ->placeholder('e.g. Manganate (FR)')
                        ->maxLength(255))->all(),
                    Textarea::make('en_dam_line')->label(__('auctions.admin.fields.en_dam_line'))->placeholder('e.g. Dam of 3 winners incl. …')->rows(4),
                    Textarea::make('ar_dam_line')->label(__('auctions.admin.fields.ar_dam_line'))->placeholder('مثال: أم لثلاثة فائزين…')->rows(4)->extraInputAttributes($rtl),
                    Textarea::make('en_note')->label(__('auctions.admin.fields.en_note'))->placeholder('e.g. Insured for death only')->rows(2),
                    Textarea::make('ar_note')->label(__('auctions.admin.fields.ar_note'))->placeholder('مثال: مؤمّن ضد النفوق فقط')->rows(2)->extraInputAttributes($rtl),
                ]),
                Tab::make(__('auctions.admin.tabs.media'))->icon('heroicon-o-photo')->schema([
                    PlaceFormFields::gallery('auctions/lots', __('auctions.admin.fields.photos'), 'photos'),
                    TagsInput::make('videos')->label(__('auctions.admin.fields.videos'))->placeholder('https://www.youtube.com/watch?v=…')
                        ->helperText(__('auctions.admin.videos_help'))->nestedRecursiveRules(['url', 'max:500']),
                    FileUpload::make('documents')->label(__('auctions.admin.fields.documents'))->helperText(__('auctions.admin.documents_help'))
                        ->disk('local')->directory('auctions/documents')->visibility('private')
                        ->multiple()->reorderable()->maxFiles(20)->maxSize(20480)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->preserveFilenames(),
                ]),
                Tab::make(__('auctions.admin.tabs.settlement'))->icon('heroicon-o-receipt-percent')->columns(3)
                    ->visible(fn (?AuctionLot $record) => $record?->status === 'sold')
                    ->schema([
                        TextEntry::make('winner')->label(__('auctions.admin.fields.winner'))->icon('heroicon-o-trophy')
                            ->state(fn (AuctionLot $record) => $record->winner ? '#'.$record->winner->paddle_number.' · '.trim(($record->winner->customer?->name ?? '').' '.($record->winner->customer?->phone ?? '')) : '—'),
                        TextEntry::make('hammer_price')->label(__('auctions.admin.fields.hammer_price'))->state(fn (AuctionLot $record) => SiteSetting::formatCurrency($record->hammer_price)),
                        TextEntry::make('invoice_state')->label(__('auctions.admin.fields.invoice'))->badge()
                            ->state(fn (AuctionLot $record) => self::invoiceState($record) ? __('auctions.admin.invoice_states.'.self::invoiceState($record)) : '—')
                            ->color(fn (AuctionLot $record) => ['paid' => 'success', 'overdue' => 'danger', 'due' => 'warning'][self::invoiceState($record)] ?? 'gray')
                            ->url(fn (AuctionLot $record) => $record->invoice_order_id ? ServiceOrderResource::getUrl('view', ['record' => $record->invoice_order_id]) : null),
                        TextEntry::make('invoice_total')->label(__('auctions.admin.fields.invoice_total'))->state(fn (AuctionLot $record) => $record->invoice ? SiteSetting::formatCurrency($record->invoice->total) : '—'),
                        TextEntry::make('payment_due_at')->label(__('auctions.admin.fields.payment_due_at'))->dateTime('j M Y, H:i')->placeholder('—'),
                        TextEntry::make('handed_over_at')->label(__('auctions.admin.fields.handed_over_at'))->dateTime('j M Y, H:i')->placeholder('—'),
                        TextEntry::make('payout_amount')->label(__('auctions.admin.fields.payout_amount'))->helperText(__('auctions.admin.payout_help'))
                            ->state(fn (AuctionLot $record) => $record->payout_amount === null ? '—' : SiteSetting::formatCurrency($record->payout_amount)),
                        TextEntry::make('payout_paid_at')->label(__('auctions.admin.fields.payout_paid_at'))->dateTime('j M Y, H:i')->placeholder('—'),
                        TextEntry::make('payout_reference')->label(__('auctions.admin.fields.payout_reference'))->placeholder('—'),
                    ]),
                Tab::make(__('auctions.admin.tabs.sale'))->icon('heroicon-o-banknotes')->columns(2)->schema([
                    Select::make('status')->label(__('auctions.admin.fields.status'))->helperText(__('auctions.admin.lot_status_help'))
                        ->options(fn (?AuctionLot $record) => collect(AuctionLot::statusOptions())
                            // sold / unsold come only from the close
                            ->only(in_array($record?->status, ['sold', 'unsold'], true) ? [$record->status] : ['draft', 'ready', 'withdrawn'])->all())
                        ->default('draft')->required()->selectablePlaceholder(false)
                        ->disabled(fn (?AuctionLot $record) => in_array($record?->status, ['sold', 'unsold'], true)),
                    TextInput::make('starting_price')->label(__('auctions.admin.fields.starting_price'))->placeholder('e.g. 1000')->numeric()->minValue(1)->required()->disabled($hasBids)->dehydrated(),
                    TextInput::make('reserve_price')->label(__('auctions.admin.fields.reserve_price'))->placeholder('e.g. 3000')->helperText(__('auctions.admin.reserve_help'))->numeric()->minValue(0),
                    TextInput::make('vat_rate')->label(__('auctions.admin.fields.vat_rate'))->placeholder('e.g. 5')->helperText(__('auctions.admin.vat_rate_help'))->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    Toggle::make('hide_result_price')->label(__('auctions.admin.fields.hide_result_price'))->inline(false),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['invoice', 'auction']))
            ->columns(self::lotColumns(withAuction: true))
            ->defaultSort('ends_at')
            ->filters([
                SelectFilter::make('auction_id')->label(__('auctions.admin.auction'))->relationship('auction', app()->getLocale() === 'ar' ? 'ar_title' : 'en_title'),
                SelectFilter::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => AuctionLot::statusOptions()),
                Filter::make('to_settle')->label(__('auctions.admin.to_settle'))
                    ->query(fn ($query) => $query->where('status', 'sold')->where(fn ($q) => $q->whereNull('payout_paid_at')->orWhereNull('handed_over_at'))),
            ])
            ->recordActions([EditAction::make()]);
    }

    /** Shared with the auction's Lots tab. */
    public static function lotColumns(bool $withAuction): array
    {
        $money = fn ($state) => $state === null ? null : SiteSetting::formatCurrency($state);

        return array_values(array_filter([
            TextColumn::make('lot_number')->label(__('auctions.admin.fields.lot_number'))->prefix('#')->sortable(),
            ImageColumn::make('photos')->label(__('auctions.admin.fields.photos'))->disk('public')->limit(1)->circular(),
            TextColumn::make(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')->label(__('auctions.admin.fields.name'))->searchable(['en_name', 'ar_name'])
                ->description(fn (AuctionLot $record) => trim(($record->sire_en ?? '').' × '.($record->dam_en ?? ''), ' ×') ?: null),
            $withAuction ? TextColumn::make('auction.'.(app()->getLocale() === 'ar' ? 'ar_title' : 'en_title'))->label(__('auctions.admin.auction'))->toggleable() : null,
            TextColumn::make('status')->label(__('auctions.admin.fields.status'))->badge()
                ->formatStateUsing(fn (string $state) => AuctionLot::statusOptions()[$state] ?? $state)
                ->color(fn (string $state) => ['ready' => 'info', 'sold' => 'success', 'unsold' => 'warning', 'withdrawn' => 'danger'][$state] ?? 'gray'),
            TextColumn::make('current_price')->label(__('auctions.admin.fields.current_price'))->formatStateUsing($money)->placeholder('—')
                ->description(fn (AuctionLot $record) => match ($record->reserveMet()) {
                    true => __('auctions.admin.reserve_met'),
                    false => __('auctions.admin.reserve_not_met'),
                    null => null,
                }),
            TextColumn::make('bids_count')->label(__('auctions.admin.fields.bids'))->icon('heroicon-o-hand-raised')
                ->description(fn (AuctionLot $record) => __('auctions.admin.bidders_count', ['count' => $record->bidders_count])),
            TextColumn::make('ends_at')->label(__('auctions.admin.fields.lot_ends_at'))->dateTime('j M, H:i:s')->sortable()->placeholder('—'),
            TextColumn::make('invoice_state')->label(__('auctions.admin.fields.invoice'))->badge()->toggleable()
                ->state(fn (AuctionLot $record) => self::invoiceState($record) ? __('auctions.admin.invoice_states.'.self::invoiceState($record)) : null)
                ->color(fn (AuctionLot $record) => ['paid' => 'success', 'overdue' => 'danger', 'due' => 'warning'][self::invoiceState($record)] ?? 'gray')
                ->placeholder('—'),
            TextColumn::make('payout_amount')->label(__('auctions.admin.fields.payout_amount'))->formatStateUsing($money)->placeholder('—')->toggleable()
                ->description(fn (AuctionLot $record) => $record->payout_paid_at ? __('auctions.admin.payout_paid') : ($record->payout_amount !== null ? __('auctions.admin.payout_open') : null)),
        ]));
    }

    /** due, overdue, paid, cancelled, or null when the lot has no invoice */
    public static function invoiceState(AuctionLot $lot): ?string
    {
        $invoice = $lot->invoice;

        return match (true) {
            $invoice === null => null,
            $invoice->isPaid() => 'paid',
            $invoice->isPayable() && $lot->payment_overdue_at !== null => 'overdue',
            $invoice->isPayable() => 'due',
            default => 'cancelled',
        };
    }

    /** "Sire › Dam › Sire" from the key (s / d per generation). */
    private static function ancestorLabel(string $key): string
    {
        return collect(str_split($key))->map(fn (string $c) => __('auctions.admin.ancestor.'.$c))->implode(' › ');
    }

    public static function getRelations(): array
    {
        return [BidsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuctionLots::route('/'),
            'create' => CreateAuctionLot::route('/create'),
            'edit' => EditAuctionLot::route('/{record}/edit'),
        ];
    }
}
