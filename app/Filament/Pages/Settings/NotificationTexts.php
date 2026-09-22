<?php

namespace App\Filament\Pages\Settings;

use App\Support\NotificationText;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Rewrite the SMS and email texts customers receive, in each language. A text left as it is (or set back
 * to the default) is not stored; the order number, link and amounts cannot be edited out of a message.
 */
class NotificationTexts extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.settings');
    }

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.settings.notification-texts';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('admin_notification_texts.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_notification_texts.title');
    }

    /** A key as a form field name: Filament reads dots as nesting. */
    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @return list<string> */
    private static function locales(): array
    {
        return array_keys(config('languages.available', ['en' => []]));
    }

    public function mount(): void
    {
        $state = [];
        $overrides = NotificationText::overrides();

        foreach (NotificationText::EDITABLE as $keys) {
            foreach ($keys as $key) {
                foreach (static::locales() as $locale) {
                    // what would be sent today: the override, or else the default
                    $state[static::field($key)][$locale] = $overrides[$key][$locale] ?? NotificationText::defaultText($key, $locale);
                }
            }
        }

        $this->form->fill(['texts' => $state]);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (NotificationText::EDITABLE as $group => $keys) {
            $sections[] = Section::make(__('admin_notification_texts.groups.'.$group))
                ->description(__('admin_notification_texts.groups.'.$group.'_desc'))
                ->collapsible()
                ->collapsed($group !== 'sms')
                ->schema(array_map(fn (string $key) => $this->textFields($key), $keys));
        }

        return $schema->components($sections)->statePath('data');
    }

    private function textFields(string $key): Grid
    {
        $placeholders = NotificationText::placeholders($key);
        $hint = $placeholders === []
            ? __('admin_notification_texts.no_placeholders')
            : __('admin_notification_texts.placeholders', ['names' => ':'.implode(', :', $placeholders)]);
        $isSms = str_starts_with($key, 'sms.');

        return Grid::make(count(static::locales()))->schema(array_map(
            fn (string $locale) => Textarea::make('texts.'.static::field($key).'.'.$locale)
                ->label(__('admin_notification_texts.labels.'.$key).' ('.config("languages.available.{$locale}.name", $locale).')')
                ->helperText($hint)
                ->rows($isSms ? 2 : 3)
                ->extraInputAttributes($locale === 'ar' ? ['dir' => 'rtl'] : [])
                // Filament evaluates this closure, which hands back a Laravel-style validation closure
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($key): void {
                    if ($error = NotificationText::validate($key, (string) $value)) {
                        $fail($error);
                    }
                }),
            static::locales(),
        ));
    }

    public function save(): void
    {
        $state = $this->form->getState()['texts'] ?? [];
        $texts = [];

        foreach (NotificationText::EDITABLE as $keys) {
            foreach ($keys as $key) {
                $texts[$key] = (array) ($state[static::field($key)] ?? []);
            }
        }

        NotificationText::save($texts);

        Notification::make()->title(__('admin_notification_texts.notifications.saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label(__('admin_notification_texts.actions.reset'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('admin_notification_texts.actions.reset_desc'))
                ->action(function (): void {
                    NotificationText::reset();
                    $this->mount();

                    Notification::make()->title(__('admin_notification_texts.notifications.reset'))->success()->send();
                }),
        ];
    }
}
