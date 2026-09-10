<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CmsSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.settings.cms-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) SiteSetting::get('module_cms_enabled', true);
    }

    public function getTitle(): string
    {
        return __('cms_settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'default_meta_description' => SiteSetting::get('default_meta_description', ''),
            'default_og_image' => SiteSetting::get('default_og_image'),
            'posts_per_page' => (string) SiteSetting::get('posts_per_page', 10),
            'sitemap_enabled' => (bool) SiteSetting::get('sitemap_enabled', true),
            'rss_enabled' => (bool) SiteSetting::get('rss_enabled', true),
            'robots_block_all' => (bool) SiteSetting::get('robots_block_all', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('cms_settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('cms_settings.tabs.general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make(__('cms_settings.sections.defaults'))
                                    ->description(__('cms_settings.sections.defaults_desc'))
                                    ->schema([
                                        Textarea::make('default_meta_description')
                                            ->label(__('cms_settings.fields.default_meta_description'))
                                            ->helperText(__('cms_settings.fields.default_meta_description_helper'))
                                            ->maxLength(300)
                                            ->rows(3),

                                        FileUpload::make('default_og_image')
                                            ->label(__('cms_settings.fields.default_og_image'))
                                            ->helperText(__('cms_settings.fields.default_og_image_helper'))
                                            ->image()
                                            ->disk('public')
                                            ->directory('cms')
                                            ->visibility('public'),

                                        TextInput::make('posts_per_page')
                                            ->label(__('cms_settings.fields.posts_per_page'))
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100)
                                            ->required(),
                                    ]),
                            ]),

                        Tab::make(__('cms_settings.tabs.seo'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make(__('cms_settings.sections.seo'))
                                    ->schema([
                                        Toggle::make('sitemap_enabled')
                                            ->label(__('cms_settings.fields.sitemap_enabled'))
                                            ->helperText(__('cms_settings.fields.sitemap_enabled_helper')),

                                        Toggle::make('rss_enabled')
                                            ->label(__('cms_settings.fields.rss_enabled'))
                                            ->helperText(__('cms_settings.fields.rss_enabled_helper')),

                                        Toggle::make('robots_block_all')
                                            ->label(__('cms_settings.fields.robots_block_all'))
                                            ->helperText(__('cms_settings.fields.robots_block_all_helper')),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SiteSetting::set('default_meta_description', (string) ($state['default_meta_description'] ?? ''), 'text', null, 'cms_settings');
        SiteSetting::set('default_og_image', (string) ($state['default_og_image'] ?? ''), 'file', null, 'cms_settings');
        SiteSetting::set('posts_per_page', (string) ($state['posts_per_page'] ?? ''), 'number', null, 'cms_settings');

        foreach (['sitemap_enabled', 'rss_enabled', 'robots_block_all'] as $key) {
            SiteSetting::set($key, !empty($state[$key]), 'boolean', null, 'cms_settings');
        }

        SiteSetting::clearCache();

        Notification::make()
            ->title(__('cms_settings.notifications.updated'))
            ->success()
            ->send();
    }
}
