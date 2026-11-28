<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionResource\Pages\CreateAuction;
use App\Filament\Resources\AuctionResource\Pages\EditAuction;
use App\Filament\Resources\AuctionResource\Pages\ListAuctions;
use App\Filament\Resources\AuctionResource\RelationManagers\BiddersRelationManager;
use App\Filament\Resources\AuctionResource\RelationManagers\LotsRelationManager;
use App\Filament\Support\PlaceFormFields;
use App\Models\Auction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Admin → Auctions: the sale itself (dates, bid steps, fees); its lots and bidders are tabs of the edit page. */
class AuctionResource extends Resource
{
    protected static ?string $model = Auction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.auctions');
    }

    public static function getModelLabel(): string
    {
        return __('auctions.admin.auction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auctions.admin.auctions');
    }

    public static function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl'];

        return $schema->components([
            Section::make(__('auctions.admin.sections.details'))
                ->icon('heroicon-o-information-circle')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('en_title')->label(__('auctions.admin.fields.en_title'))->placeholder('e.g. Winter Arabian horse sale 2026')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, ?Auction $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('ar_title')->label(__('auctions.admin.fields.ar_title'))->placeholder('مثال: مزاد الخيول العربية الشتوي ٢٠٢٦')->required()->maxLength(255)->extraInputAttributes($rtl),
                    TextInput::make('slug')->label(__('auctions.admin.fields.slug'))->placeholder('winter-sale-2026')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true)->notIn(['sell']),
                    Select::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => Auction::statusOptions())->default('draft')->required()->selectablePlaceholder(false),
                    Select::make('organizer_id')->label(__('auctions.admin.fields.organizer'))->helperText(__('auctions.admin.organizer_help'))
                        ->relationship('organizer', app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')->placeholder(__('auctions.admin.organizer_placeholder')),
                    Toggle::make('auto_approve')->label(__('auctions.admin.fields.auto_approve'))->helperText(__('auctions.admin.auto_approve_help'))->inline(false),
                    Textarea::make('en_description')->label(__('auctions.admin.fields.en_description'))->placeholder('e.g. 24 young Arabian horses from Omani breeders')->rows(3),
                    Textarea::make('ar_description')->label(__('auctions.admin.fields.ar_description'))->placeholder('مثال: ٢٤ حصانًا عربيًا من مربين عمانيين')->rows(3)->extraInputAttributes($rtl),
                    PlaceFormFields::coverPhoto('auctions', __('auctions.admin.fields.cover_photo'))->columnSpanFull(),
                    RichEditor::make('en_conditions')->label(__('auctions.admin.fields.en_conditions'))->placeholder('The auction conditions every bidder accepts')->columnSpanFull(),
                    RichEditor::make('ar_conditions')->label(__('auctions.admin.fields.ar_conditions'))->placeholder('شروط المزاد التي يقبلها كل مزايد')->extraInputAttributes($rtl)->columnSpanFull(),
                ]),
            Section::make(__('auctions.admin.sections.schedule'))
                ->icon('heroicon-o-clock')
                ->description(__('auctions.admin.schedule_help'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DateTimePicker::make('preview_at')->label(__('auctions.admin.fields.preview_at'))->placeholder('2026-12-01 12:00')->native(false)->seconds(false)->required(),
                    DateTimePicker::make('bidding_starts_at')->label(__('auctions.admin.fields.bidding_starts_at'))->placeholder('2026-12-05 18:00')->native(false)->seconds(false)->required()->afterOrEqual('preview_at'),
                    DateTimePicker::make('ends_at')->label(__('auctions.admin.fields.ends_at'))->placeholder('2026-12-08 21:00')->helperText(__('auctions.admin.ends_at_help'))->native(false)->seconds(false)->required()->after('bidding_starts_at'),
                    TextInput::make('lot_gap_seconds')->label(__('auctions.admin.fields.lot_gap_seconds'))->placeholder('120')->helperText(__('auctions.admin.lot_gap_help'))->integer()->minValue(0)->maxValue(3600)->default(120)->required(),
                    TextInput::make('extension_minutes')->label(__('auctions.admin.fields.extension_minutes'))->placeholder('3')->helperText(__('auctions.admin.extension_help'))->integer()->minValue(1)->maxValue(30)->default(3)->required(),
                ]),
            Section::make(__('auctions.admin.sections.increments'))
                ->icon('heroicon-o-arrow-trending-up')
                ->description(__('auctions.admin.increments_help'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('increments')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('from')->label(__('auctions.admin.fields.increment_from'))->placeholder('e.g. 5000')->numeric()->minValue(0)->required(),
                            TextInput::make('step')->label(__('auctions.admin.fields.increment_step'))->placeholder('e.g. 100')->numeric()->minValue(0.001)->required(),
                        ])
                        ->columns(2)
                        ->default(Auction::DEFAULT_INCREMENTS)
                        ->minItems(1)
                        ->addActionLabel(__('auctions.admin.add_increment')),
                ]),
            Section::make(__('auctions.admin.sections.money'))
                ->icon('heroicon-o-banknotes')
                ->description(__('auctions.admin.money_help'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('deposit_amount')->label(__('auctions.admin.fields.deposit_amount'))->placeholder('e.g. 100')->numeric()->minValue(0)->default(0),
                    TextInput::make('buyer_premium_percent')->label(__('auctions.admin.fields.buyer_premium_percent'))->placeholder('e.g. 5')->numeric()->minValue(0)->maxValue(50)->suffix('%')->default(0),
                    TextInput::make('seller_commission_percent')->label(__('auctions.admin.fields.seller_commission_percent'))->placeholder('e.g. 5')->numeric()->minValue(0)->maxValue(50)->suffix('%')->default(0),
                    TextInput::make('entry_fee')->label(__('auctions.admin.fields.entry_fee'))->placeholder('e.g. 50')->numeric()->minValue(0)->default(0),
                    TextInput::make('payment_days')->label(__('auctions.admin.fields.payment_days'))->placeholder('3')->integer()->minValue(1)->maxValue(60)->default(3)->required(),
                    Textarea::make('en_payment_note')->label(__('auctions.admin.fields.en_payment_note'))->helperText(__('auctions.admin.payment_note_help'))
                        ->placeholder('e.g. Bank Muscat, account 0123-456789-001, IBAN OM12 0270 …, reference: your lot number')->rows(3)->columnSpan(['default' => 3, 'lg' => 1]),
                    Textarea::make('ar_payment_note')->label(__('auctions.admin.fields.ar_payment_note'))
                        ->placeholder('مثال: بنك مسقط، الحساب ‎0123-456789-001، المرجع: رقم الحصان')->rows(3)->extraInputAttributes(['dir' => 'rtl'])->columnSpan(['default' => 3, 'lg' => 1]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $title = app()->getLocale() === 'ar' ? 'ar_title' : 'en_title';

        return $table
            ->columns([
                TextColumn::make($title)->label(__('auctions.admin.fields.title'))->searchable(['en_title', 'ar_title'])->sortable(),
                TextColumn::make('organizer.'.(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name'))->label(__('auctions.admin.fields.organizer'))->placeholder('—')->toggleable(),
                TextColumn::make('status')->label(__('auctions.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => Auction::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => ['published' => 'success', 'cancelled' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('bidding_starts_at')->label(__('auctions.admin.fields.bidding_starts_at'))->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('ends_at')->label(__('auctions.admin.fields.ends_at'))->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('lots_count')->label(__('auctions.admin.lots'))->counts('lots')->icon('heroicon-o-rectangle-stack'),
                TextColumn::make('bidders_count')->label(__('auctions.admin.bidders'))->counts('bidders')->icon('heroicon-o-user-group'),
            ])
            ->defaultSort('bidding_starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => Auction::statusOptions()),
            ])
            ->recordActions([
                Action::make('page')->label(__('auctions.admin.view_on_site'))->icon('heroicon-o-globe-alt')
                    ->url(fn (Auction $record) => route('auctions.show', $record->slug))->openUrlInNewTab()
                    ->visible(fn (Auction $record) => $record->status === 'published'),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [LotsRelationManager::class, BiddersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuctions::route('/'),
            'create' => CreateAuction::route('/create'),
            'edit' => EditAuction::route('/{record}/edit'),
        ];
    }
}
