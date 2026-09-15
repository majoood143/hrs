<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdResource\Pages\CreateAd;
use App\Filament\Resources\AdResource\Pages\EditAd;
use App\Filament\Resources\AdResource\Pages\ListAds;
use App\Filament\Support\TranslatableInput;
use App\Models\Ad;
use App\Models\AdZone;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AdResource extends Resource
{
    protected static ?string $model = Ad::class;
    protected static ?string $slug = 'promotions';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?int $navigationSort = 94;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('ads.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ads.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('ads.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('zone_id')
                    ->label(__('ads.fields.zone'))
                    ->relationship('zone', 'id')
                    ->getOptionLabelFromRecordUsing(fn (AdZone $record) => $record->name)
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('customer_name')
                    ->label(__('ads.fields.customer_name'))
                    ->helperText(__('ads.fields.customer_name_helper'))
                    ->required()
                    ->maxLength(255),

                Select::make('media_type')
                    ->label(__('ads.fields.media_type'))
                    ->options([
                        'image' => __('ads.fields.media_type_image'),
                        'video' => __('ads.fields.media_type_video'),
                    ])
                    ->default('image')
                    ->native(false)
                    ->live()
                    ->required()
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label(__('ads.fields.image'))
                    ->image()
                    ->disk('public')
                    ->directory('promo/images')
                    ->visible(fn ($get) => $get('media_type') === 'image')
                    ->required(fn ($get) => $get('media_type') === 'image')
                    ->columnSpanFull(),

                FileUpload::make('video_path')
                    ->label(__('ads.fields.video'))
                    ->helperText(__('ads.fields.video_helper'))
                    ->disk('public')
                    ->directory('promo/videos')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                    ->maxSize(20480)
                    ->visible(fn ($get) => $get('media_type') === 'video')
                    ->required(fn ($get) => $get('media_type') === 'video')
                    ->columnSpanFull(),

                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("alt_text.{$code}")
                    ->label(__('ads.fields.alt_text') . ' (' . $meta['native'] . ')'))
                    ->columnSpanFull(),

                TextInput::make('target_url')
                    ->label(__('ads.fields.target_url'))
                    ->helperText(__('ads.fields.target_url_helper'))
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    DateTimePicker::make('starts_at')
                        ->label(__('ads.fields.starts_at'))
                        ->native(false)
                        ->seconds(false),

                    DateTimePicker::make('ends_at')
                        ->label(__('ads.fields.ends_at'))
                        ->native(false)
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('order')
                        ->label(__('ads.fields.order'))
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label(__('ads.fields.is_active'))
                        ->default(true),
                ]),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->getStateUsing(fn (Ad $record) => $record->media_type === 'image' && $record->image_path
                        ? Storage::disk('public')->url($record->image_path)
                        : null),

                TextColumn::make('customer_name')
                    ->label(__('ads.fields.customer_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('zone.name')
                    ->label(__('ads.fields.zone'))
                    ->badge(),

                TextColumn::make('media_type')
                    ->label(__('ads.fields.media_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("ads.fields.media_type_{$state}")),

                TextColumn::make('starts_at')
                    ->label(__('ads.fields.starts_at'))
                    ->dateTime('M j, Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('ads.fields.ends_at'))
                    ->dateTime('M j, Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('ads.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("ads.statuses.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('order')
                    ->label(__('ads.fields.order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('ads.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('zone_id')
                    ->label(__('ads.filters.zone'))
                    ->relationship('zone', 'id')
                    ->getOptionLabelFromRecordUsing(fn (AdZone $record) => $record->name),

                TernaryFilter::make('is_active')
                    ->label(__('ads.filters.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('ads.empty_state.heading'))
            ->emptyStateDescription(__('ads.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAds::route('/'),
            'create' => CreateAd::route('/create'),
            'edit' => EditAd::route('/{record}/edit'),
        ];
    }
}
