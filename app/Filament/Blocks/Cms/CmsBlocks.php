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
use Filament\Schemas\Components\Grid;

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
            static::columns(),
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
                static::headingField(),
                static::subheadingField(),
                FileUpload::make('background_image')
                    ->label(__('cms.blocks.background_image'))
                    ->image()
                    ->disk('public')
                    ->directory('cms/hero')
                    ->columnSpanFull(),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("primary_button_text.{$code}")
                    ->label(__('cms.blocks.primary_button_text') . ' (' . $meta['native'] . ')')),
                TextInput::make('primary_button_url')->label(__('cms.blocks.primary_button_url')),
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("secondary_button_text.{$code}")
                    ->label(__('cms.blocks.secondary_button_text') . ' (' . $meta['native'] . ')')),
                TextInput::make('secondary_button_url')->label(__('cms.blocks.secondary_button_url')),
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

    public static function columns(): Block
    {
        return Block::make('columns')
            ->label(__('cms.blocks.columns'))
            ->icon('heroicon-o-view-columns')
            ->schema([
                Repeater::make('items')
                    ->label(__('cms.blocks.items'))
                    ->schema([
                        TranslatableInput::grid(fn ($code, $meta) => TextInput::make("heading.{$code}")
                            ->label(__('cms.blocks.heading') . ' (' . $meta['native'] . ')')
                            ->required($code === TranslatableInput::defaultLocale()))
                            ->columnSpanFull(),
                        TranslatableInput::grid(fn ($code, $meta) => Textarea::make("text.{$code}")
                            ->label(__('cms.blocks.text') . ' (' . $meta['native'] . ')')
                            ->rows(3))
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->defaultItems(2),
            ]);
    }

    public static function divider(): Block
    {
        return Block::make('divider')
            ->label(__('cms.blocks.divider'))
            ->icon('heroicon-o-minus');
    }
}
