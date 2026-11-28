<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionOrganizerResource\Pages\ManageAuctionOrganizers;
use App\Filament\Support\PlaceFormFields;
use App\Models\AuctionOrganizer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Admin → Auctions → Organisers: who runs a sale (a stud, a club). An auction without one is run by the site. */
class AuctionOrganizerResource extends Resource
{
    protected static ?string $model = AuctionOrganizer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.auctions');
    }

    public static function getModelLabel(): string
    {
        return __('auctions.admin.organizer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auctions.admin.organizers');
    }

    public static function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl'];

        return $schema->columns(2)->components([
            TextInput::make('en_name')->label(__('auctions.admin.fields.en_name'))->placeholder('e.g. Muscat Arabian Stud')->required()->maxLength(255),
            TextInput::make('ar_name')->label(__('auctions.admin.fields.ar_name'))->placeholder('مثال: مربط مسقط للخيل العربية')->required()->maxLength(255)->extraInputAttributes($rtl),
            PlaceFormFields::logo('auctions/organizers', __('auctions.admin.fields.logo')),
            ColorPicker::make('colour')->label(__('auctions.admin.fields.colour_header'))->helperText(__('auctions.admin.colour_help'))->placeholder('#7a1f1f')->regex('/^#[0-9a-fA-F]{6}$/'),
            Textarea::make('en_about')->label(__('auctions.admin.fields.en_about'))->placeholder('e.g. Breeding Arabian horses in Oman since 1990')->rows(3),
            Textarea::make('ar_about')->label(__('auctions.admin.fields.ar_about'))->placeholder('مثال: نربي الخيل العربية في عُمان منذ ١٩٩٠')->rows(3)->extraInputAttributes($rtl),
            TextInput::make('phone')->label(__('auctions.admin.fields.phone'))->placeholder('e.g. 9123 4567')->tel()->maxLength(32),
            TextInput::make('email')->label(__('auctions.admin.fields.email'))->placeholder('sales@example.com')->email()->maxLength(255),
            TextInput::make('website')->label(__('auctions.admin.fields.website'))->placeholder('https://example.com')->url()->maxLength(255)->columnSpanFull(),
            Repeater::make('team')->label(__('auctions.admin.fields.team'))->columnSpanFull()->columns(4)->defaultItems(0)
                ->addActionLabel(__('auctions.admin.add_team_member'))
                ->schema([
                    TextInput::make('name')->label(__('auctions.admin.fields.name'))->placeholder('e.g. Ahmed Al Busaidi')->required()->maxLength(255),
                    TextInput::make('en_role')->label(__('auctions.admin.fields.en_role'))->placeholder('e.g. Sales manager')->maxLength(255),
                    TextInput::make('ar_role')->label(__('auctions.admin.fields.ar_role'))->placeholder('مثال: مدير المبيعات')->maxLength(255)->extraInputAttributes($rtl),
                    TextInput::make('phone')->label(__('auctions.admin.fields.phone'))->placeholder('e.g. 9123 4567')->tel()->maxLength(32),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label(__('auctions.admin.fields.logo'))->disk('public')->circular(),
                TextColumn::make(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')->label(__('auctions.admin.fields.name'))->searchable(['en_name', 'ar_name']),
                ColorColumn::make('colour')->label(__('auctions.admin.fields.colour_header')),
                TextColumn::make('auctions_count')->label(__('auctions.admin.auctions'))->counts('auctions')->icon('heroicon-o-megaphone'),
            ])
            ->recordActions([
                EditAction::make(),
                // its auctions fall back to the site's own header (organizer_id is nulled)
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuctionOrganizers::route('/')];
    }
}
