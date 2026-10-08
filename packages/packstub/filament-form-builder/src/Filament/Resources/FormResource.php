<?php

namespace Packstub\FormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Filament\EditorLanguages;
use Packstub\FormBuilder\Filament\FieldBlocks;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

class FormResource extends Resource
{
    protected static ?string $slug = 'forms';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModel(): string
    {
        return FormBuilder::formModel();
    }

    public static function getModelLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::plugin()?->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return static::plugin()?->getNavigationIcon() ?? 'heroicon-o-document-text';
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (static::plugin()?->hasNavigationBadge() ?? true)) {
            return null;
        }

        $unread = FormBuilder::submissionModel()::query()->unread()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function canAccess(): bool
    {
        return (static::plugin()?->isAuthorized() ?? true) && parent::canAccess();
    }

    protected static function plugin(): ?FormBuilderPlugin
    {
        try {
            return FormBuilderPlugin::get();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Above the tabs, so a new form's name is the first thing asked, not a
                // "required" error on a tab nobody opened; folded away once the form exists.
                EditorLanguages::switcher(),
                static::generalSection(),
                Tabs::make()
                    ->tabs([
                        Tab::make(__('packstub-form-builder::form-builder.tabs.fields'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([FieldBlocks::make('fields')]),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema(static::settingsSchema()),
                        ...app(FormBuilder::class)->formTabs(),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.embed'))
                            ->icon('heroicon-o-code-bracket')
                            ->schema(static::embedSchema())
                            ->hidden(fn (?Model $record): bool => $record === null),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    protected static function generalSection(): Section
    {
        return Section::make(__('packstub-form-builder::form-builder.sections.general'))
            ->icon('heroicon-o-identification')
            ->columns(2)
            ->collapsible()
            ->collapsed(fn (?Model $record): bool => $record !== null)
            ->schema([
                EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("name.{$locale}")
                    ->label(__('packstub-form-builder::form-builder.fields.name').' ('.$meta['native'].')')
                    // the slug is always made from the English name: APP_LOCALE differs between
                    // servers, and Str::slug() on Arabic gives nothing usable
                    ->required($locale === 'en')
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Model $record) use ($locale): void {
                        if ($locale === 'en' && $record === null && blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }))->columnSpanFull(),
                TextInput::make('slug')
                    ->label(__('packstub-form-builder::form-builder.fields.slug'))
                    ->helperText(__('packstub-form-builder::form-builder.fields.slug_hint'))
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label(__('packstub-form-builder::form-builder.fields.is_active'))
                    ->helperText(__('packstub-form-builder::form-builder.fields.is_active_hint'))
                    ->default(true),
                EditorLanguages::grid(fn (string $locale, array $meta): Textarea => Textarea::make("description.{$locale}")
                    ->label(__('packstub-form-builder::form-builder.fields.description').' ('.$meta['native'].')')
                    ->rows(2))->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function settingsSchema(): array
    {
        return [
            Section::make(__('packstub-form-builder::form-builder.sections.after_submit'))
                ->columns(2)
                ->schema([
                    EditorLanguages::grid(fn (string $locale, array $meta): TextInput => TextInput::make("submit_label.{$locale}")
                        ->label(__('packstub-form-builder::form-builder.fields.submit_label').' ('.$meta['native'].')')
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.submit'))
                        ->maxLength(100))->columnSpanFull(),
                    TextInput::make('redirect_url')
                        ->label(__('packstub-form-builder::form-builder.fields.redirect_url'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.redirect_url_hint'))
                        ->placeholder('/thank-you')
                        ->maxLength(2048)
                        // a full web address or a path on this site; anything else would send the visitor nowhere
                        ->regex('#^(https?://[^\s]+|/[^\s]*)$#i')
                        ->validationMessages(['regex' => __('packstub-form-builder::form-builder.fields.redirect_url_invalid')])
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    EditorLanguages::grid(fn (string $locale, array $meta): Textarea => Textarea::make("success_message.{$locale}")
                        ->label(__('packstub-form-builder::form-builder.fields.success_message').' ('.$meta['native'].')')
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.success'))
                        // kept (not disabled: a disabled field would be emptied on save), but said to be unused
                        ->helperText(fn (Get $get): ?string => filled($get('redirect_url'))
                            ? __('packstub-form-builder::form-builder.fields.success_message_redirect')
                            : null)
                        ->rows(2))->columnSpanFull(),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.notifications'))
                ->columns(2)
                ->schema([
                    TagsInput::make('notification_emails')
                        ->label(__('packstub-form-builder::form-builder.fields.notification_emails'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notification_emails_hint'))
                        ->placeholder('name@example.com')
                        ->nestedRecursiveRules(['email'])
                        ->live(),
                    Toggle::make('store_submissions')
                        ->label(__('packstub-form-builder::form-builder.fields.store_submissions'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.store_submissions_hint'))
                        ->default(true)
                        ->inline(false)
                        ->live(),
                    // a warning, not a rule: a sink or the host app may still take the submissions
                    Callout::make(__('packstub-form-builder::form-builder.fields.nothing_kept'))
                        ->description(__('packstub-form-builder::form-builder.fields.nothing_kept_hint'))
                        ->warning()
                        ->visible(fn (Get $get): bool => ! $get('store_submissions') && blank(array_filter((array) $get('notification_emails'))))
                        ->columnSpanFull(),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.availability'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    DateTimePicker::make('opens_at')
                        ->label(__('packstub-form-builder::form-builder.fields.opens_at'))
                        ->helperText(fn (DateTimePicker $component): string => __('packstub-form-builder::form-builder.fields.timezone_hint', ['timezone' => $component->getTimezone()]))
                        ->native(),
                    DateTimePicker::make('closes_at')
                        ->label(__('packstub-form-builder::form-builder.fields.closes_at'))
                        ->helperText(fn (DateTimePicker $component): string => __('packstub-form-builder::form-builder.fields.timezone_hint', ['timezone' => $component->getTimezone()]))
                        ->native()
                        ->after('opens_at'),
                    Toggle::make('settings.require_login')
                        ->label(__('packstub-form-builder::form-builder.fields.require_login'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.require_login_hint'))
                        ->default(false),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.spam'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Toggle::make('settings.honeypot')
                        ->label(__('packstub-form-builder::form-builder.fields.honeypot'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.honeypot_hint'))
                        ->default((bool) config('packstub-form-builder.spam.honeypot', true))
                        ->inline(false),
                    TextInput::make('settings.min_seconds')
                        ->label(__('packstub-form-builder::form-builder.fields.min_seconds'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.min_seconds_hint'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(600)
                        ->default((int) config('packstub-form-builder.spam.min_seconds', 2)),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function embedSchema(): array
    {
        $snippet = fn (string $name, string $label, \Closure $state, ?string $hint = null): TextEntry => TextEntry::make($name)
            ->label($label)
            ->helperText($hint)
            ->state($state)
            ->copyable()
            ->copyMessage(__('packstub-form-builder::form-builder.embed.copied'))
            ->fontFamily(FontFamily::Mono)
            ->columnSpanFull();

        return [
            Section::make(__('packstub-form-builder::form-builder.embed.heading'))
                ->schema([
                    $snippet('embed_blade', __('packstub-form-builder::form-builder.embed.blade'), fn (Form $record): string => '<x-form-builder::form form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.blade_hint')),
                    $snippet('embed_livewire', __('packstub-form-builder::form-builder.embed.livewire'), fn (Form $record): string => '<livewire:form-builder form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.livewire_hint')),
                    $snippet('embed_page', __('packstub-form-builder::form-builder.embed.page'), fn (Form $record): string => $record->pageUrl() ?? '—')
                        ->hidden(fn (Form $record): bool => $record->pageUrl() === null),
                    $snippet('embed_json', __('packstub-form-builder::form-builder.embed.json'), fn (Form $record): string => 'GET '.$record->definitionUrl().PHP_EOL.'POST '.$record->submitUrl(), __('packstub-form-builder::form-builder.embed.json_hint')),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('packstub-form-builder::form-builder.fields.name'))
                    ->description(fn (Form $record): string => $record->slug)
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('packstub-form-builder::form-builder.fields.submissions_count'))
                    ->counts('submissions')
                    ->icon('heroicon-o-inbox-stack')
                    ->url(fn (Form $record): ?string => static::submissionsUrl($record))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('unread_submissions_count')
                    ->label(__('packstub-form-builder::form-builder.fields.unread_count'))
                    ->counts(['submissions as unread_submissions_count' => fn (Builder $query) => $query->whereNull('read_at')])
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (int|string|null $state): ?string => (int) $state > 0 ? (string) $state : null)
                    ->url(fn (Form $record): ?string => static::submissionsUrl($record))
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label(__('packstub-form-builder::form-builder.fields.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('packstub-form-builder::form-builder.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            // a click on a form opens what it received; the editor is one tab away
            ->recordUrl(fn (Form $record): ?string => static::submissionsUrl($record)
                ?? (static::canEdit($record) ? Pages\EditForm::getUrl(['record' => $record]) : null))
            ->filters([
                TernaryFilter::make('is_active')->label(__('packstub-form-builder::form-builder.fields.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('submissions')
                            ->label(__('packstub-form-builder::form-builder.submissions.plural'))
                            ->icon('heroicon-o-inbox-stack')
                            ->url(fn (Form $record): ?string => static::submissionsUrl($record))
                            ->visible(fn (Form $record): bool => static::submissionsUrl($record) !== null),
                        EditAction::make(),
                        ...app(FormBuilder::class)->recordActions(),
                        Action::make('open')
                            ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (Form $record): ?string => $record->pageUrl(), shouldOpenInNewTab: true)
                            ->visible(fn (Form $record): bool => $record->pageUrl() !== null),
                    ])->dropdown(false),
                    ActionGroup::make([DeleteAction::make()])->dropdown(false),
                ]),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * A form's tabs: the editor, its submissions, then any page the host app registered.
     *
     * @return array<NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\EditForm::class,
            Pages\ManageSubmissions::class,
            ...array_column(app(FormBuilder::class)->resourcePages(), 'page'),
        ]);
    }

    /** The form's submissions page, or null for someone who may not read them. */
    public static function submissionsUrl(Form $record): ?string
    {
        return Pages\ManageSubmissions::canAccess(['record' => $record])
            ? Pages\ManageSubmissions::getUrl(['record' => $record])
            : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
            'submissions' => Pages\ManageSubmissions::route('/{record}/submissions'),
            ...collect(app(FormBuilder::class)->resourcePages())
                ->map(fn (array $page) => $page['page']::route($page['path']))
                ->all(),
        ];
    }
}
