<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.settings');
    }

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings.general-settings';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('general_settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('general_settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'site_name_en' => SiteSetting::get('site_name_en', config('app.name', 'HRS')),
            'site_name_ar' => SiteSetting::get('site_name_ar', ''),
            'timezone' => SiteSetting::get('timezone', 'Asia/Muscat'),
            'time_format' => SiteSetting::get('time_format', '24'),
            'currency_code' => SiteSetting::get('currency_code', 'OMR'),
            'currency_symbol' => SiteSetting::get('currency_symbol', 'OMR'),
            'currency_icon' => SiteSetting::get('currency_icon'),

            'contact_phone' => SiteSetting::get('contact_phone', ''),
            'contact_email' => SiteSetting::get('contact_email', ''),
            'footer_text_en' => SiteSetting::get('footer_text_en', ''),
            'footer_text_ar' => SiteSetting::get('footer_text_ar', ''),

            'site_logo' => SiteSetting::get('site_logo'),
            'app_logo' => SiteSetting::get('app_logo'),
            'favicon' => SiteSetting::get('favicon'),
            'primary_color' => SiteSetting::get('primary_color', '#05602b'),
            'secondary_color' => SiteSetting::get('secondary_color', '#0da74c'),
            'accent_color' => SiteSetting::get('accent_color', '#0ea5e9'),
            'panel_primary_color' => SiteSetting::get('panel_primary_color', '#16a34a'),
            'button_color' => SiteSetting::get('button_color', ''),
            'button_text_color' => SiteSetting::get('button_text_color', '#ffffff'),
            'heading_font' => SiteSetting::get('heading_font', 'fraunces'),
            'body_font' => SiteSetting::get('body_font', 'inter'),

            'min_tickets_per_booking' => (string) SiteSetting::get('min_tickets_per_booking', 1),
            'max_tickets_per_booking' => (string) SiteSetting::get('max_tickets_per_booking', 10),
            'max_attendee_age_years' => (string) SiteSetting::get('max_attendee_age_years', 75),
            'pending_booking_expiry_minutes' => (string) SiteSetting::get('pending_booking_expiry_minutes', 15),

            'show_email' => (bool) SiteSetting::get('show_email', true),
            'show_phone' => (bool) SiteSetting::get('show_phone', true),
            'show_date_of_birth' => (bool) SiteSetting::get('show_date_of_birth', true),
            'show_gender' => (bool) SiteSetting::get('show_gender', true),
            'show_nationality' => (bool) SiteSetting::get('show_nationality', true),
            'show_identity_number' => (bool) SiteSetting::get('show_identity_number', true),
            'show_slot_end_time' => (bool) SiteSetting::get('show_slot_end_time', true),

            'checkin_date_restriction_enabled' => (bool) SiteSetting::get('checkin_date_restriction_enabled', false),
            'checkin_date_restriction_mode' => SiteSetting::get('checkin_date_restriction_mode', 'block'),
            'checkin_date_grace_days' => (string) SiteSetting::get('checkin_date_grace_days', 0),

            'terms_en' => SiteSetting::get('terms_en', ''),
            'terms_ar' => SiteSetting::get('terms_ar', ''),
            'terms_url_en' => SiteSetting::get('terms_url_en', ''),
            'terms_url_ar' => SiteSetting::get('terms_url_ar', ''),

            'module_kiosk_enabled' => (bool) SiteSetting::get('module_kiosk_enabled', true),
            'module_extra_services_enabled' => (bool) SiteSetting::get('module_extra_services_enabled', true),
            'module_private_events_enabled' => (bool) SiteSetting::get('module_private_events_enabled', true),
            'module_promo_codes_enabled' => (bool) SiteSetting::get('module_promo_codes_enabled', true),
            'module_expenses_enabled' => (bool) SiteSetting::get('module_expenses_enabled', true),
            'module_commission_enabled' => (bool) SiteSetting::get('module_commission_enabled', true),
            'module_cms_enabled' => (bool) SiteSetting::get('module_cms_enabled', true),
            'module_pos_enabled' => (bool) SiteSetting::get('module_pos_enabled', true),
            'module_pos_shop_enabled' => (bool) SiteSetting::get('module_pos_shop_enabled', true),

            'social_facebook_url' => SiteSetting::get('social_facebook_url', ''),
            'social_instagram_url' => SiteSetting::get('social_instagram_url', ''),
            'social_x_url' => SiteSetting::get('social_x_url', ''),
            'social_linkedin_url' => SiteSetting::get('social_linkedin_url', ''),
            'social_youtube_url' => SiteSetting::get('social_youtube_url', ''),
            'social_tiktok_url' => SiteSetting::get('social_tiktok_url', ''),
            'social_whatsapp_url' => SiteSetting::get('social_whatsapp_url', ''),

            'seo_meta_description' => SiteSetting::get('seo_meta_description', ''),
            'seo_og_image' => SiteSetting::get('seo_og_image'),

            'google_tag_manager_id' => SiteSetting::get('google_tag_manager_id', ''),

            'success_page_back_url' => SiteSetting::get('success_page_back_url', ''),
            'success_page_message_en' => SiteSetting::get('success_page_message_en', ''),
            'success_page_message_ar' => SiteSetting::get('success_page_message_ar', ''),
            'success_page_message_color' => SiteSetting::get('success_page_message_color', 'blue'),

            'image_compression_enabled' => (bool) SiteSetting::get('image_compression_enabled', true),
            'image_compression_quality' => (string) SiteSetting::get('image_compression_quality', 75),
            'image_compression_max_width' => (string) SiteSetting::get('image_compression_max_width', 2000),
            'image_compression_max_height' => (string) SiteSetting::get('image_compression_max_height', 2000),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('general_settings.tabs.general'))
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make(__('general_settings.sections.site_identity'))
                                    ->description(__('general_settings.sections.site_identity_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('site_name_en')
                                                ->label(__('general_settings.fields.site_name_en'))
                                                ->required()
                                                ->maxLength(255),

                                            TextInput::make('site_name_ar')
                                                ->label(__('general_settings.fields.site_name_ar'))
                                                ->required()
                                                ->maxLength(255),
                                        ]),
                                    ]),

                                Section::make(__('general_settings.sections.contact_info'))
                                    ->description(__('general_settings.sections.contact_info_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('contact_phone')
                                                ->label(__('general_settings.fields.contact_phone'))
                                                ->tel()
                                                ->maxLength(50),

                                            TextInput::make('contact_email')
                                                ->label(__('general_settings.fields.contact_email'))
                                                ->email()
                                                ->maxLength(255),
                                        ]),
                                    ]),

                                Section::make(__('general_settings.sections.footer'))
                                    ->description(__('general_settings.sections.footer_desc'))
                                    ->schema([
                                        RichEditor::make('footer_text_en')
                                            ->label(__('general_settings.fields.footer_text_en'))
                                            ->fileAttachmentsDisk('public'),

                                        RichEditor::make('footer_text_ar')
                                            ->label(__('general_settings.fields.footer_text_ar'))
                                            ->fileAttachmentsDisk('public'),
                                    ]),

                                Section::make(__('general_settings.sections.localization'))
                                    ->description(__('general_settings.sections.localization_desc'))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('timezone')
                                                ->label(__('general_settings.fields.timezone'))
                                                ->options(collect(\DateTimeZone::listIdentifiers())
                                                    ->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                                ->searchable()
                                                ->required()
                                                ->native(false),

                                            Select::make('time_format')
                                                ->label(__('general_settings.fields.time_format'))
                                                ->options([
                                                    '24' => __('general_settings.fields.time_format_24'),
                                                    '12' => __('general_settings.fields.time_format_12'),
                                                ])
                                                ->required()
                                                ->native(false),

                                            Select::make('currency_code')
                                                ->label(__('general_settings.fields.currency_code'))
                                                ->options([
                                                    'OMR' => 'OMR — Omani Rial',
                                                    'USD' => 'USD — US Dollar',
                                                    'AED' => 'AED — UAE Dirham',
                                                    'SAR' => 'SAR — Saudi Riyal',
                                                    'QAR' => 'QAR — Qatari Riyal',
                                                    'KWD' => 'KWD — Kuwaiti Dinar',
                                                    'BHD' => 'BHD — Bahraini Dinar',
                                                    'EUR' => 'EUR — Euro',
                                                    'GBP' => 'GBP — British Pound',
                                                ])
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->native(false)
                                                ->afterStateUpdated(fn ($state, $set) => $set('currency_symbol', $state)),

                                            TextInput::make('currency_symbol')
                                                ->label(__('general_settings.fields.currency_symbol'))
                                                ->required()
                                                ->maxLength(10),
                                        ]),

                                        FileUpload::make('currency_icon')
                                            ->label(__('general_settings.fields.currency_icon'))
                                            ->helperText(__('general_settings.fields.currency_icon_helper'))
                                            ->acceptedFileTypes(['image/svg+xml'])
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public'),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.branding'))
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Section::make(__('general_settings.sections.logos'))
                                    ->description(__('general_settings.sections.logos_desc'))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            FileUpload::make('site_logo')
                                                ->label(__('general_settings.fields.site_logo'))
                                                ->helperText(__('general_settings.fields.site_logo_helper'))
                                                ->image()
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public'),

                                            FileUpload::make('app_logo')
                                                ->label(__('general_settings.fields.app_logo'))
                                                ->helperText(__('general_settings.fields.app_logo_helper'))
                                                ->image()
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public'),

                                            FileUpload::make('favicon')
                                                ->label(__('general_settings.fields.favicon'))
                                                ->helperText(__('general_settings.fields.favicon_helper'))
                                                ->image()
                                                ->disk('public')
                                                ->directory('branding')
                                                ->visibility('public'),
                                        ]),
                                    ]),

                                Section::make(__('general_settings.sections.colors'))
                                    ->description(__('general_settings.sections.colors_desc'))
                                    ->schema([
                                        Grid::make(4)->schema([
                                            ColorPicker::make('primary_color')
                                                ->label(__('general_settings.fields.primary_color'))
                                                ->helperText(__('general_settings.fields.primary_color_helper')),

                                            ColorPicker::make('secondary_color')
                                                ->label(__('general_settings.fields.secondary_color'))
                                                ->helperText(__('general_settings.fields.secondary_color_helper')),

                                            ColorPicker::make('accent_color')
                                                ->label(__('general_settings.fields.accent_color'))
                                                ->helperText(__('general_settings.fields.accent_color_helper')),

                                            ColorPicker::make('panel_primary_color')
                                                ->label(__('general_settings.fields.panel_primary_color'))
                                                ->helperText(__('general_settings.fields.panel_primary_color_helper')),
                                        ]),

                                        Grid::make(2)->schema([
                                            ColorPicker::make('button_color')
                                                ->label(__('general_settings.fields.button_color'))
                                                ->helperText(__('general_settings.fields.button_color_helper')),

                                            ColorPicker::make('button_text_color')
                                                ->label(__('general_settings.fields.button_text_color'))
                                                ->helperText(__('general_settings.fields.button_text_color_helper')),
                                        ]),
                                    ]),

                                Section::make(__('general_settings.sections.typography'))
                                    ->description(__('general_settings.sections.typography_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('heading_font')
                                                ->label(__('general_settings.fields.heading_font'))
                                                ->helperText(__('general_settings.fields.heading_font_helper'))
                                                ->options(collect(config('fonts', []))->map(fn ($font) => $font['label']))
                                                ->native(false)
                                                ->required(),

                                            Select::make('body_font')
                                                ->label(__('general_settings.fields.body_font'))
                                                ->helperText(__('general_settings.fields.body_font_helper'))
                                                ->options(collect(config('fonts', []))->map(fn ($font) => $font['label']))
                                                ->native(false)
                                                ->required(),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.media'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make(__('general_settings.sections.image_compression'))
                                    ->description(__('general_settings.sections.image_compression_desc'))
                                    ->schema([
                                        Toggle::make('image_compression_enabled')
                                            ->label(__('general_settings.fields.image_compression_enabled'))
                                            ->helperText(__('general_settings.fields.image_compression_enabled_helper'))
                                            ->live(),

                                        Grid::make(3)->schema([
                                            TextInput::make('image_compression_quality')
                                                ->label(__('general_settings.fields.image_compression_quality'))
                                                ->helperText(__('general_settings.fields.image_compression_quality_helper'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(100)
                                                ->suffix('%')
                                                ->required()
                                                ->visible(fn (Get $get) => (bool) $get('image_compression_enabled')),

                                            TextInput::make('image_compression_max_width')
                                                ->label(__('general_settings.fields.image_compression_max_width'))
                                                ->helperText(__('general_settings.fields.image_compression_max_width_helper'))
                                                ->numeric()
                                                ->minValue(100)
                                                ->suffix('px')
                                                ->required()
                                                ->visible(fn (Get $get) => (bool) $get('image_compression_enabled')),

                                            TextInput::make('image_compression_max_height')
                                                ->label(__('general_settings.fields.image_compression_max_height'))
                                                ->helperText(__('general_settings.fields.image_compression_max_height_helper'))
                                                ->numeric()
                                                ->minValue(100)
                                                ->suffix('px')
                                                ->required()
                                                ->visible(fn (Get $get) => (bool) $get('image_compression_enabled')),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.booking_rules'))
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Section::make(__('general_settings.sections.booking_rules'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('min_tickets_per_booking')
                                                ->label(__('general_settings.fields.min_tickets_per_booking'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->required(),

                                            TextInput::make('max_tickets_per_booking')
                                                ->label(__('general_settings.fields.max_tickets_per_booking'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(1000)
                                                ->required(),

                                            TextInput::make('max_attendee_age_years')
                                                ->label(__('general_settings.fields.max_attendee_age_years'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(120)
                                                ->required(),

                                            TextInput::make('pending_booking_expiry_minutes')
                                                ->label(__('general_settings.fields.pending_booking_expiry_minutes'))
                                                ->helperText(__('general_settings.fields.pending_booking_expiry_minutes_helper'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->required(),
                                        ]),

                                        Toggle::make('show_slot_end_time')
                                            ->label(__('general_settings.fields.show_slot_end_time'))
                                            ->helperText(__('general_settings.fields.show_slot_end_time_helper')),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.check_in'))
                            ->icon('heroicon-o-qr-code')
                            ->schema([
                                Section::make(__('general_settings.sections.check_in'))
                                    ->description(__('general_settings.sections.check_in_desc'))
                                    ->schema([
                                        Toggle::make('checkin_date_restriction_enabled')
                                            ->label(__('general_settings.fields.checkin_date_restriction_enabled'))
                                            ->helperText(__('general_settings.fields.checkin_date_restriction_enabled_helper'))
                                            ->live(),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('checkin_date_restriction_mode')
                                                    ->label(__('general_settings.fields.checkin_date_restriction_mode'))
                                                    ->helperText(__('general_settings.fields.checkin_date_restriction_mode_helper'))
                                                    ->options([
                                                        'block' => __('general_settings.fields.checkin_date_restriction_mode_block'),
                                                        'warn' => __('general_settings.fields.checkin_date_restriction_mode_warn'),
                                                    ])
                                                    ->native(false)
                                                    ->required(),

                                                TextInput::make('checkin_date_grace_days')
                                                    ->label(__('general_settings.fields.checkin_date_grace_days'))
                                                    ->helperText(__('general_settings.fields.checkin_date_grace_days_helper'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(30)
                                                    ->required(),
                                            ])
                                            ->visible(fn (Get $get) => (bool) $get('checkin_date_restriction_enabled')),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.attendee_fields'))
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make(__('general_settings.sections.attendee_fields'))
                                    ->description(__('general_settings.sections.attendee_fields_desc'))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('show_email')
                                                ->label(__('general_settings.fields.show_email')),

                                            Toggle::make('show_phone')
                                                ->label(__('general_settings.fields.show_phone')),

                                            Toggle::make('show_date_of_birth')
                                                ->label(__('general_settings.fields.show_date_of_birth')),

                                            Toggle::make('show_gender')
                                                ->label(__('general_settings.fields.show_gender')),

                                            Toggle::make('show_nationality')
                                                ->label(__('general_settings.fields.show_nationality')),

                                            Toggle::make('show_identity_number')
                                                ->label(__('general_settings.fields.show_identity_number')),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.terms'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make(__('general_settings.fields.terms_en'))
                                    ->schema([
                                        RichEditor::make('terms_en')
                                            ->label('')
                                            ->fileAttachmentsDisk('public'),
                                    ]),

                                Section::make(__('general_settings.fields.terms_ar'))
                                    ->schema([
                                        RichEditor::make('terms_ar')
                                            ->label('')
                                            ->fileAttachmentsDisk('public'),
                                    ]),

                                Section::make(__('general_settings.sections.terms_url'))
                                    ->description(__('general_settings.sections.terms_url_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('terms_url_en')
                                                ->label(__('general_settings.fields.terms_url_en'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('terms_url_ar')
                                                ->label(__('general_settings.fields.terms_url_ar'))
                                                ->url()
                                                ->maxLength(255),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.modules'))
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make(__('general_settings.sections.modules'))
                                    ->description(__('general_settings.sections.modules_desc'))
                                    ->schema([
                                        Toggle::make('module_kiosk_enabled')
                                            ->label(__('general_settings.fields.module_kiosk_enabled'))
                                            ->helperText(__('general_settings.fields.module_kiosk_enabled_helper')),

                                        Toggle::make('module_extra_services_enabled')
                                            ->label(__('general_settings.fields.module_extra_services_enabled'))
                                            ->helperText(__('general_settings.fields.module_extra_services_enabled_helper')),

                                        Toggle::make('module_private_events_enabled')
                                            ->label(__('general_settings.fields.module_private_events_enabled'))
                                            ->helperText(__('general_settings.fields.module_private_events_enabled_helper')),

                                        Toggle::make('module_promo_codes_enabled')
                                            ->label(__('general_settings.fields.module_promo_codes_enabled'))
                                            ->helperText(__('general_settings.fields.module_promo_codes_enabled_helper')),

                                        Toggle::make('module_expenses_enabled')
                                            ->label(__('general_settings.fields.module_expenses_enabled'))
                                            ->helperText(__('general_settings.fields.module_expenses_enabled_helper')),

                                        Toggle::make('module_commission_enabled')
                                            ->label(__('general_settings.fields.module_commission_enabled'))
                                            ->helperText(__('general_settings.fields.module_commission_enabled_helper')),

                                        Toggle::make('module_cms_enabled')
                                            ->label(__('general_settings.fields.module_cms_enabled'))
                                            ->helperText(__('general_settings.fields.module_cms_enabled_helper')),

                                        Toggle::make('module_pos_enabled')
                                            ->label(__('general_settings.fields.module_pos_enabled'))
                                            ->helperText(__('general_settings.fields.module_pos_enabled_helper')),

                                        Toggle::make('module_pos_shop_enabled')
                                            ->label(__('general_settings.fields.module_pos_shop_enabled'))
                                            ->helperText(__('general_settings.fields.module_pos_shop_enabled_helper')),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.social_media'))
                            ->icon('heroicon-o-share')
                            ->schema([
                                Section::make(__('general_settings.sections.social_media'))
                                    ->description(__('general_settings.sections.social_media_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('social_facebook_url')
                                                ->label(__('general_settings.fields.social_facebook_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_instagram_url')
                                                ->label(__('general_settings.fields.social_instagram_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_x_url')
                                                ->label(__('general_settings.fields.social_x_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_linkedin_url')
                                                ->label(__('general_settings.fields.social_linkedin_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_youtube_url')
                                                ->label(__('general_settings.fields.social_youtube_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_tiktok_url')
                                                ->label(__('general_settings.fields.social_tiktok_url'))
                                                ->url()
                                                ->maxLength(255),

                                            TextInput::make('social_whatsapp_url')
                                                ->label(__('general_settings.fields.social_whatsapp_url'))
                                                ->helperText(__('general_settings.fields.social_whatsapp_url_helper'))
                                                ->url()
                                                ->maxLength(255),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.success_page'))
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Section::make(__('general_settings.sections.success_page_link'))
                                    ->description(__('general_settings.sections.success_page_link_desc'))
                                    ->schema([
                                        TextInput::make('success_page_back_url')
                                            ->label(__('general_settings.fields.success_page_back_url'))
                                            ->helperText(__('general_settings.fields.success_page_back_url_helper'))
                                            ->url()
                                            ->maxLength(255),
                                    ]),

                                Section::make(__('general_settings.sections.success_page_message'))
                                    ->description(__('general_settings.sections.success_page_message_desc'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Textarea::make('success_page_message_en')
                                                ->label(__('general_settings.fields.success_page_message_en'))
                                                ->maxLength(500)
                                                ->rows(3),

                                            Textarea::make('success_page_message_ar')
                                                ->label(__('general_settings.fields.success_page_message_ar'))
                                                ->maxLength(500)
                                                ->rows(3),
                                        ]),

                                        Select::make('success_page_message_color')
                                            ->label(__('general_settings.fields.success_page_message_color'))
                                            ->options([
                                                'gray' => __('general_settings.fields.color_gray'),
                                                'blue' => __('general_settings.fields.color_blue'),
                                                'green' => __('general_settings.fields.color_green'),
                                                'red' => __('general_settings.fields.color_red'),
                                                'yellow' => __('general_settings.fields.color_yellow'),
                                                'purple' => __('general_settings.fields.color_purple'),
                                            ])
                                            ->native(false)
                                            ->required(),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.seo'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make(__('general_settings.sections.seo'))
                                    ->description(__('general_settings.sections.seo_desc'))
                                    ->schema([
                                        Textarea::make('seo_meta_description')
                                            ->label(__('general_settings.fields.seo_meta_description'))
                                            ->helperText(__('general_settings.fields.seo_meta_description_helper'))
                                            ->maxLength(300)
                                            ->rows(3),

                                        FileUpload::make('seo_og_image')
                                            ->label(__('general_settings.fields.seo_og_image'))
                                            ->helperText(__('general_settings.fields.seo_og_image_helper'))
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public'),
                                    ]),
                            ]),

                        Tab::make(__('general_settings.tabs.integrations'))
                            ->icon('heroicon-o-puzzle-piece')
                            ->schema([
                                Section::make(__('general_settings.sections.google_tag_manager'))
                                    ->description(__('general_settings.sections.google_tag_manager_desc'))
                                    ->schema([
                                        TextInput::make('google_tag_manager_id')
                                            ->label(__('general_settings.fields.google_tag_manager_id'))
                                            ->helperText(__('general_settings.fields.google_tag_manager_id_helper'))
                                            ->regex('/^GTM-[A-Za-z0-9]+$/')
                                            ->maxLength(20)
                                            ->placeholder('GTM-XXXXXXX'),
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

        $typed = [
            'site_name_en' => 'text',
            'site_name_ar' => 'text',
            'contact_phone' => 'text',
            'contact_email' => 'text',
            'footer_text_en' => 'richtext',
            'footer_text_ar' => 'richtext',
            'timezone' => 'text',
            'time_format' => 'text',
            'currency_code' => 'text',
            'currency_symbol' => 'text',
            'currency_icon' => 'file',
            'site_logo' => 'file',
            'app_logo' => 'file',
            'favicon' => 'file',
            'primary_color' => 'color',
            'secondary_color' => 'color',
            'accent_color' => 'color',
            'panel_primary_color' => 'color',
            'button_color' => 'color',
            'button_text_color' => 'color',
            'heading_font' => 'text',
            'body_font' => 'text',
            'min_tickets_per_booking' => 'number',
            'max_tickets_per_booking' => 'number',
            'max_attendee_age_years' => 'number',
            'pending_booking_expiry_minutes' => 'number',
            'checkin_date_restriction_mode' => 'text',
            'checkin_date_grace_days' => 'number',
            'terms_en' => 'richtext',
            'terms_ar' => 'richtext',
            'terms_url_en' => 'text',
            'terms_url_ar' => 'text',
            'social_facebook_url' => 'text',
            'social_instagram_url' => 'text',
            'social_x_url' => 'text',
            'social_linkedin_url' => 'text',
            'social_youtube_url' => 'text',
            'social_tiktok_url' => 'text',
            'social_whatsapp_url' => 'text',
            'seo_meta_description' => 'text',
            'seo_og_image' => 'file',
            'google_tag_manager_id' => 'text',
            'success_page_back_url' => 'text',
            'success_page_message_en' => 'text',
            'success_page_message_ar' => 'text',
            'success_page_message_color' => 'text',
            'image_compression_quality' => 'number',
            'image_compression_max_width' => 'number',
            'image_compression_max_height' => 'number',
        ];

        foreach ($typed as $key => $type) {
            SiteSetting::set($key, (string) ($state[$key] ?? ''), $type, null, 'general_settings');
        }

        $booleans = [
            'show_email',
            'show_phone',
            'show_date_of_birth',
            'show_gender',
            'show_nationality',
            'show_identity_number',
            'show_slot_end_time',
            'checkin_date_restriction_enabled',
            'module_kiosk_enabled',
            'module_extra_services_enabled',
            'module_private_events_enabled',
            'module_promo_codes_enabled',
            'module_expenses_enabled',
            'module_commission_enabled',
            'module_cms_enabled',
            'module_pos_enabled',
            'module_pos_shop_enabled',
            'image_compression_enabled',
        ];

        foreach ($booleans as $key) {
            SiteSetting::set($key, !empty($state[$key]), 'boolean', null, 'general_settings');
        }

        SiteSetting::clearCache();

        Notification::make()
            ->title(__('general_settings.notifications.updated'))
            ->success()
            ->send();
    }
}
