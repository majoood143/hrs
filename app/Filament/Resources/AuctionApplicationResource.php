<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionApplicationResource\Pages\EditAuctionApplication;
use App\Filament\Resources\AuctionApplicationResource\Pages\ListAuctionApplications;
use App\Filament\Support\PlaceFormFields;
use App\Models\AuctionApplication;
use App\Models\AuctionLot;
use App\Models\SiteSetting;
use App\Models\Stallion;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Admin → Auctions → Seller applications: horses sellers sent from /auctions/sell, approved into a lot or rejected. */
class AuctionApplicationResource extends Resource
{
    protected static ?string $model = AuctionApplication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.auctions');
    }

    public static function getModelLabel(): string
    {
        return __('auctions.admin.application');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auctions.admin.applications');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = AuctionApplication::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $pending = fn (?AuctionApplication $record) => $record?->status === 'pending';

        return $schema->components([
            Section::make(__('auctions.admin.sections.application'))->icon('heroicon-o-user')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('customer')->label(__('auctions.admin.fields.customer'))->icon('heroicon-o-phone')
                    ->state(fn (AuctionApplication $record) => trim(($record->customer?->name ?? '').' · '.($record->customer?->phone ?? ''), ' ·')),
                TextEntry::make('status')->label(__('auctions.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => AuctionApplication::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => ['approved' => 'success', 'rejected' => 'danger'][$state] ?? 'warning'),
                TextEntry::make('created_at')->label(__('auctions.admin.fields.sent_at'))->dateTime('j M Y, H:i'),
                TextEntry::make('reject_reason')->label(__('auctions.admin.fields.reject_reason'))->visible(fn (?AuctionApplication $record) => $record?->status === 'rejected')->columnSpanFull(),
            ]),
            Section::make(__('auctions.admin.tabs.horse'))->icon('heroicon-o-information-circle')->columns(2)->columnSpanFull()->disabled(fn (?AuctionApplication $record) => ! $pending($record))->schema([
                TextInput::make('horse_name')->label(__('auctions.sell.fields.horse_name'))->placeholder('e.g. Bahar Muscat')->required()->maxLength(255),
                Select::make('gender')->label(__('auctions.sell.fields.gender'))->options(fn () => AuctionLot::genderOptions())->required(),
                DatePicker::make('born_on')->label(__('auctions.sell.fields.born_on'))->placeholder('2023-03-14')->native(false),
                Select::make('colour')->label(__('auctions.sell.fields.colour'))->options(fn () => Stallion::colourOptions()),
                TextInput::make('breed')->label(__('auctions.sell.fields.breed'))->placeholder('e.g. Purebred Arabian')->maxLength(255),
                TextInput::make('height_cm')->label(__('auctions.sell.fields.height_cm'))->placeholder('e.g. 152')->integer()->minValue(50)->maxValue(220),
                TextInput::make('sire')->label(__('auctions.sell.fields.sire'))->placeholder('e.g. Amer (SA)')->maxLength(255),
                TextInput::make('dam')->label(__('auctions.sell.fields.dam'))->placeholder('e.g. Nisaee (FR)')->maxLength(255),
                TextInput::make('ueln')->label(__('auctions.sell.fields.ueln'))->placeholder('e.g. 512001202300123')->maxLength(64),
                TextInput::make('microchip')->label(__('auctions.sell.fields.microchip'))->placeholder('e.g. 512098100012345')->maxLength(64),
                TextInput::make('owner_name')->label(__('auctions.sell.fields.owner_name'))->placeholder('e.g. Salim Al Harthy')->required()->maxLength(255),
                TextInput::make('owner_town')->label(__('auctions.sell.fields.owner_town'))->placeholder('e.g. Seeb')->maxLength(255),
                TextInput::make('breeder_name')->label(__('auctions.sell.fields.breeder_name'))->placeholder('e.g. Muscat Stud')->maxLength(255),
                TextInput::make('asking_reserve')->label(__('auctions.sell.fields.asking_reserve'))->placeholder('e.g. 3000')->numeric()->minValue(0),
                Textarea::make('notes')->label(__('auctions.sell.fields.notes'))->placeholder('e.g. Shown twice in 2025')->rows(3)->columnSpanFull(),
            ]),
            Section::make(__('auctions.admin.tabs.media'))->icon('heroicon-o-photo')->columnSpanFull()->disabled(fn (?AuctionApplication $record) => ! $pending($record))->schema([
                PlaceFormFields::gallery('auctions/applications', __('auctions.sell.fields.photos'), 'photos'),
                TagsInput::make('videos')->label(__('auctions.sell.fields.videos'))->placeholder('https://www.youtube.com/watch?v=…')->nestedRecursiveRules(['url', 'max:500']),
                FileUpload::make('documents')->label(__('auctions.sell.fields.documents'))->helperText(__('auctions.admin.documents_help'))
                    ->disk('local')->directory('auctions/documents')->visibility('private')->multiple()->maxFiles(10)->maxSize(20480)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])->downloadable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'lot.auction']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('photos')->label(__('auctions.sell.fields.photos'))->disk('public')->limit(1)->circular(),
                TextColumn::make('horse_name')->label(__('auctions.sell.fields.horse_name'))->searchable()
                    ->description(fn (AuctionApplication $record) => trim(($record->sire ?? '').' × '.($record->dam ?? ''), ' ×') ?: null),
                TextColumn::make('gender')->label(__('auctions.sell.fields.gender'))->formatStateUsing(fn (string $state) => AuctionLot::genderOptions()[$state] ?? $state),
                TextColumn::make('customer.phone')->label(__('auctions.admin.fields.customer'))->searchable()
                    ->description(fn (AuctionApplication $record) => $record->customer?->name),
                TextColumn::make('asking_reserve')->label(__('auctions.sell.fields.asking_reserve'))->formatStateUsing(fn ($state) => $state === null ? null : SiteSetting::formatCurrency($state))->placeholder('—'),
                TextColumn::make('status')->label(__('auctions.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => AuctionApplication::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => ['approved' => 'success', 'rejected' => 'danger'][$state] ?? 'warning')
                    ->description(fn (AuctionApplication $record) => $record->lot ? __('auctions.admin.in_auction', ['auction' => $record->lot->auction?->title, 'number' => $record->lot->lot_number]) : null),
                TextColumn::make('created_at')->label(__('auctions.admin.fields.sent_at'))->dateTime('j M Y, H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('auctions.admin.fields.status'))->options(fn () => AuctionApplication::statusOptions())->default('pending'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuctionApplications::route('/'),
            'edit' => EditAuctionApplication::route('/{record}/edit'),
        ];
    }
}
