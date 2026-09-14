<?php

namespace App\Filament\Blocks\Cms;

use App\Filament\Support\TranslatableInput;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Packstub\FormBuilder\Models\Form as FormBuilderForm;

class CmsBlocks
{
    /**
     * @return array<int, Block>
     */
    public static function all(): array
    {
        return [
            static::hero(),
            static::searchPreview(),
            static::transferBoard(),
            static::featuredHorses(),
            static::successStories(),
            static::partners(),
            static::statsCounter(),
            static::cta(),
            static::heading(),
            static::richText(),
            static::image(),
            static::video(),
            static::columns(),
            static::htmlEmbed(),
            static::form(),
            static::divider(),
        ];
    }

    private static function headingField(bool $required = true): Grid
    {
        return TranslatableInput::grid(fn ($code, $meta) => TextInput::make("heading.{$code}")
            ->label(__('cms.blocks.heading') . ' (' . $meta['native'] . ')')
            ->required($required && $code === TranslatableInput::defaultLocale()));
    }

    private static function subheadingField(): Grid
    {
        return TranslatableInput::grid(fn ($code, $meta) => Textarea::make("subheading.{$code}")
            ->label(__('cms.blocks.subheading') . ' (' . $meta['native'] . ')')
            ->rows(2));
    }

    public static function hero(): Block
    {
        return Block::make('hero')
            ->label(__('cms.blocks.hero'))
            ->icon('heroicon-o-photo')
            ->schema([
                Repeater::make('slides')
                    ->label(__('cms.blocks.slides'))
                    ->schema([
                        static::headingField(),
                        static::subheadingField(),
                        FileUpload::make('background_image')
                            ->label(__('cms.blocks.background_image'))
                            ->image()
                            ->disk('public')
                            ->directory('cms/hero')
                            ->required()
                            ->columnSpanFull(),
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("primary_button_text.{$code}")
                            ->label(__('cms.blocks.primary_button_text') . ' (' . $meta['native'] . ')')),
                        TextInput::make('primary_button_url')->label(__('cms.blocks.primary_button_url')),
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("secondary_button_text.{$code}")
                            ->label(__('cms.blocks.secondary_button_text') . ' (' . $meta['native'] . ')')),
                        TextInput::make('secondary_button_url')->label(__('cms.blocks.secondary_button_url')),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => \App\Support\Localized::value($state, 'heading') ?: __('cms.blocks.slide'))
                    ->columnSpanFull(),
                Toggle::make('autoplay')
                    ->label(__('cms.blocks.autoplay'))
                    ->default(true),
                TextInput::make('autoplay_delay')
                    ->label(__('cms.blocks.autoplay_delay'))
                    ->helperText(__('cms.blocks.autoplay_delay_helper'))
                    ->numeric()
                    ->default(6000)
                    ->minValue(2000)
                    ->suffix('ms'),
            ])
            ->columns(2);
    }

    public static function searchPreview(): Block
    {
        return Block::make('search_preview')
            ->label(__('cms.blocks.search_preview'))
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                static::headingField(),
                static::subheadingField(),
            ]);
    }

    public static function transferBoard(): Block
    {
        return Block::make('transfer_board')
            ->label(__('cms.blocks.transfer_board'))
            ->icon('heroicon-o-truck')
            ->schema([
                static::headingField(required: false),
                static::subheadingField(),
            ]);
    }

    public static function featuredHorses(): Block
    {
        return Block::make('featured_horses')
            ->label(__('cms.blocks.featured_horses'))
            ->icon('heroicon-o-rectangle-stack')
            ->schema([
                static::headingField(),
                static::subheadingField(),
                TextInput::make('count')->label(__('cms.blocks.count'))->numeric()->default(6)->minValue(1)->maxValue(12),
            ]);
    }

    public static function successStories(): Block
    {
        return Block::make('success_stories')
            ->label(__('cms.blocks.success_stories'))
            ->icon('heroicon-o-heart')
            ->schema([
                static::headingField(),
                static::subheadingField(),
                TextInput::make('count')->label(__('cms.blocks.count'))->numeric()->default(3)->minValue(1)->maxValue(9),
            ]);
    }

    public static function partners(): Block
    {
        return Block::make('partners')
            ->label(__('cms.blocks.partners'))
            ->icon('heroicon-o-building-storefront')
            ->schema([
                static::headingField(),
                static::subheadingField(),
            ]);
    }

    public static function statsCounter(): Block
    {
        return Block::make('stats_counter')
            ->label(__('cms.blocks.stats_counter'))
            ->icon('heroicon-o-chart-bar')
            ->schema([
                static::headingField(required: false),
                Repeater::make('stats')
                    ->label(__('cms.blocks.stats'))
                    ->schema([
                        TextInput::make('number')->label(__('cms.blocks.number'))->required(),
                        TextInput::make('suffix')->label(__('cms.blocks.suffix')),
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("label.{$code}")
                            ->label(__('cms.blocks.label') . ' (' . $meta['native'] . ')')
                            ->required($code === TranslatableInput::defaultLocale()))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(3)
                    ->reorderable(false),
            ]);
    }

    public static function cta(): Block
    {
        return Block::make('cta')
            ->label(__('cms.blocks.cta'))
            ->icon('heroicon-o-megaphone')
            ->schema([
                static::headingField(),
                static::subheadingField(),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("button_text.{$code}")
                    ->label(__('cms.blocks.button_text') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale())),
                TextInput::make('button_url')->label(__('cms.blocks.button_url'))->required(),
                Select::make('style')
                    ->label(__('cms.blocks.style'))
                    ->options([
                        'warm' => __('cms.blocks.style_warm'),
                        'dark' => __('cms.blocks.style_dark'),
                    ])
                    ->default('warm')
                    ->native(false),
            ])
            ->columns(2);
    }

    public static function heading(): Block
    {
        return Block::make('heading')
            ->label(__('cms.blocks.heading_block'))
            ->icon('heroicon-o-bars-3-bottom-left')
            ->schema([
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("text.{$code}")
                    ->label(__('cms.blocks.text') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale()))
                    ->columnSpanFull(),
                Select::make('level')
                    ->label(__('cms.blocks.level'))
                    ->options(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                    ->default('h2')
                    ->native(false),
                Select::make('alignment')
                    ->label(__('cms.blocks.alignment'))
                    ->options(['left' => __('cms.blocks.left'), 'center' => __('cms.blocks.center'), 'right' => __('cms.blocks.right')])
                    ->default('center')
                    ->native(false),
            ])
            ->columns(2);
    }

    public static function richText(): Block
    {
        return Block::make('rich_text')
            ->label(__('cms.blocks.rich_text'))
            ->icon('heroicon-o-document-text')
            ->schema([
                TranslatableInput::grid(fn ($code, $meta) => RichEditor::make("content.{$code}")
                    ->label(__('cms.blocks.content') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale()), columns: 1),
            ]);
    }

    public static function image(): Block
    {
        return Block::make('image')
            ->label(__('cms.blocks.image'))
            ->icon('heroicon-o-photo')
            ->schema([
                FileUpload::make('image')
                    ->label(__('cms.blocks.image'))
                    ->image()
                    ->disk('public')
                    ->directory('cms/images')
                    ->required()
                    ->columnSpanFull(),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("caption.{$code}")
                    ->label(__('cms.blocks.caption') . ' (' . $meta['native'] . ')')),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("alt.{$code}")
                    ->label(__('cms.blocks.alt') . ' (' . $meta['native'] . ')')),
            ]);
    }

    public static function video(): Block
    {
        return Block::make('video')
            ->label(__('cms.blocks.video'))
            ->icon('heroicon-o-play-circle')
            ->schema([
                Textarea::make('url')
                    ->label(__('cms.blocks.video_url'))
                    ->helperText(__('cms.blocks.video_url_helper'))
                    ->rows(2)
                    ->required()
                    ->columnSpanFull(),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("caption.{$code}")
                    ->label(__('cms.blocks.caption') . ' (' . $meta['native'] . ')')),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function columnIconOptions(): array
    {
        return [
            'heroicon-o-trophy' => __('cms.blocks.icon_trophy'),
            'heroicon-o-shield-check' => __('cms.blocks.icon_shield_check'),
            'heroicon-o-truck' => __('cms.blocks.icon_truck'),
            'heroicon-o-users' => __('cms.blocks.icon_users'),
            'heroicon-o-chart-bar' => __('cms.blocks.icon_chart_bar'),
            'heroicon-o-map-pin' => __('cms.blocks.icon_map_pin'),
            'heroicon-o-calendar' => __('cms.blocks.icon_calendar'),
            'heroicon-o-currency-dollar' => __('cms.blocks.icon_currency_dollar'),
            'heroicon-o-academic-cap' => __('cms.blocks.icon_academic_cap'),
            'heroicon-o-heart' => __('cms.blocks.icon_heart'),
            'heroicon-o-star' => __('cms.blocks.icon_star'),
            'heroicon-o-globe-alt' => __('cms.blocks.icon_globe_alt'),
            'heroicon-o-clock' => __('cms.blocks.icon_clock'),
            'heroicon-o-check-circle' => __('cms.blocks.icon_check_circle'),
            'heroicon-o-sparkles' => __('cms.blocks.icon_sparkles'),
            'heroicon-o-building-storefront' => __('cms.blocks.icon_building_storefront'),
        ];
    }

    public static function columns(): Block
    {
        return Block::make('columns')
            ->label(__('cms.blocks.columns'))
            ->icon('heroicon-o-view-columns')
            ->schema([
                Select::make('columns_per_row')
                    ->label(__('cms.blocks.columns_per_row'))
                    ->options([
                        'auto' => __('cms.blocks.columns_per_row_auto'),
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ])
                    ->default('auto')
                    ->native(false),
                Select::make('card_style')
                    ->label(__('cms.blocks.card_style'))
                    ->options([
                        'card' => __('cms.blocks.card_style_card'),
                        'minimal' => __('cms.blocks.card_style_minimal'),
                        'bordered' => __('cms.blocks.card_style_bordered'),
                    ])
                    ->default('card')
                    ->native(false),
                Select::make('alignment')
                    ->label(__('cms.blocks.alignment'))
                    ->options([
                        'left' => __('cms.blocks.left'),
                        'center' => __('cms.blocks.center'),
                    ])
                    ->default('left')
                    ->native(false),
                Repeater::make('items')
                    ->label(__('cms.blocks.items'))
                    ->schema([
                        Select::make('media_type')
                            ->label(__('cms.blocks.media_type'))
                            ->options([
                                'none' => __('cms.blocks.media_type_none'),
                                'icon' => __('cms.blocks.media_type_icon'),
                                'image' => __('cms.blocks.media_type_image'),
                            ])
                            ->default('none')
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),
                        Select::make('icon')
                            ->label(__('cms.blocks.icon'))
                            ->options(static::columnIconOptions())
                            ->native(false)
                            ->visible(fn ($get) => $get('media_type') === 'icon')
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label(__('cms.blocks.image'))
                            ->image()
                            ->disk('public')
                            ->directory('cms/columns')
                            ->visible(fn ($get) => $get('media_type') === 'image')
                            ->columnSpanFull(),
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("heading.{$code}")
                            ->label(__('cms.blocks.heading') . ' (' . $meta['native'] . ')')
                            ->required($code === TranslatableInput::defaultLocale()))
                            ->columnSpanFull(),
                        TranslatableInput::grid(fn ($code, $meta) => Textarea::make("text.{$code}")
                            ->label(__('cms.blocks.text') . ' (' . $meta['native'] . ')')
                            ->rows(3))
                            ->columnSpanFull(),
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("link_label.{$code}")
                            ->label(__('cms.blocks.link_label') . ' (' . $meta['native'] . ')'))
                            ->columnSpanFull(),
                        TextInput::make('link_url')
                            ->label(__('cms.blocks.link_url'))
                            ->url()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->defaultItems(2)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => \App\Support\Localized::value($state, 'heading') ?: __('cms.blocks.item'))
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public static function htmlEmbed(): Block
    {
        return Block::make('html_embed')
            ->label(__('cms.blocks.html_embed'))
            ->icon('heroicon-o-code-bracket')
            ->schema([
                Textarea::make('code')
                    ->label(__('cms.blocks.html_embed_code'))
                    ->helperText(__('cms.blocks.html_embed_code_helper'))
                    ->rows(10)
                    ->required()
                    ->extraInputAttributes(['class' => 'font-mono text-sm', 'dir' => 'ltr'])
                    ->columnSpanFull(),
                Select::make('container')
                    ->label(__('cms.blocks.html_embed_container'))
                    ->options([
                        'boxed' => __('cms.blocks.html_embed_container_boxed'),
                        'full' => __('cms.blocks.html_embed_container_full'),
                    ])
                    ->default('boxed')
                    ->native(false),
            ]);
    }

    public static function form(): Block
    {
        return Block::make('form')
            ->label(__('cms.blocks.form'))
            ->icon('heroicon-o-clipboard-document-check')
            ->schema([
                static::headingField(required: false),
                static::subheadingField(),
                Select::make('form_slug')
                    ->label(__('cms.blocks.form_select'))
                    ->helperText(__('cms.blocks.form_select_helper'))
                    ->options(fn () => FormBuilderForm::query()->where('is_active', true)->orderBy('name')->pluck('name', 'slug'))
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),
                Select::make('container')
                    ->label(__('cms.blocks.html_embed_container'))
                    ->options([
                        'boxed' => __('cms.blocks.html_embed_container_boxed'),
                        'full' => __('cms.blocks.html_embed_container_full'),
                    ])
                    ->default('boxed')
                    ->native(false),
            ])
            ->columns(2);
    }

    public static function divider(): Block
    {
        return Block::make('divider')
            ->label(__('cms.blocks.divider'))
            ->icon('heroicon-o-minus');
    }
}
