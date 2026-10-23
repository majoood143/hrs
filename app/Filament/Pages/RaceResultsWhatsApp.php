<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Settings\GeneralSettings;
use App\Models\RaceResultAlert;
use App\Services\Racing\RaceResultAlerts;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingUnavailableException;
use App\Services\WhatsApp\RaceResultMessage;
use App\Support\PhoneNumber;
use App\Support\WhatsAppSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * A race day's races and what was sent to the admins by WhatsApp for each: preview, send, send again,
 * or send every race not sent yet. The automatic check (racing:result-alerts) sends today's on its own
 * when "Race results" is on in General Settings → WhatsApp.
 */
class RaceResultsWhatsApp extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $slug = 'race-results-whatsapp';

    protected string $view = 'filament.pages.race-results-whatsapp';

    public bool $unavailable = false;

    /** @var ?array<string, string> date => label, worked out once per request */
    private ?array $days = null;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_race_results.navigation');
    }

    public function getTitle(): string
    {
        return __('admin_race_results.title');
    }

    public function autoStatus(): string
    {
        $count = count(WhatsAppSettings::recipients());

        return match (true) {
            $count === 0 => __('admin_race_results.no_recipients'),
            RaceResultAlerts::autoEnabled() => __('admin_race_results.auto_on', ['count' => $count, 'wait' => (int) config('racing.result_alert_video_wait', 20)]),
            default => __('admin_race_results.auto_off'),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendAll')
                ->label(__('admin_race_results.actions.send_all'))
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalDescription(__('admin_race_results.send_all_confirm'))
                ->disabled(fn () => WhatsAppSettings::recipients() === [])
                ->action(fn () => $this->sendAll()),
            Action::make('settings')
                ->label(__('admin_race_results.settings'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(GeneralSettings::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters) => $this->rows($filters['date']['value'] ?? null))
            ->paginated(false)
            ->filters([
                SelectFilter::make('date')
                    ->label(__('admin_race_results.day'))
                    ->options(fn () => $this->days())
                    ->default(fn () => array_key_first($this->days()))
                    ->selectablePlaceholder(false),
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                TextColumn::make('no')->label(__('admin_race_results.columns.no')),
                TextColumn::make('time')->label(__('admin_race_results.columns.time'))->placeholder('—'),
                TextColumn::make('name')
                    ->label(__('admin_race_results.columns.race'))
                    ->description(fn (array $record) => $record['distance'] ? $record['distance'].' m' : null)
                    ->wrap(),
                TextColumn::make('results')
                    ->label(__('admin_race_results.columns.results'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => __('admin_race_results.results_'.($state ? 'yes' : 'no')))
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextColumn::make('video')
                    ->label(__('admin_race_results.columns.replay'))
                    ->formatStateUsing(fn () => __('admin_race_results.watch'))
                    ->icon('heroicon-o-play-circle')
                    ->url(fn (array $record) => $record['video'], shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('admin_race_results.columns.status'))
                    ->badge()
                    ->state(fn (array $record) => $this->status($record['alert']))
                    ->color(fn (array $record) => match (true) {
                        $record['alert']?->sent_at !== null => 'success',
                        $record['alert']?->results_seen_at !== null => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (array $record) => $this->statusDetail($record['alert'])),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('admin_race_results.actions.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (array $record) => $record['results'])
                    ->modalHeading(fn (array $record) => $record['name'])
                    ->modalContent(fn (array $record) => $this->previewHtml($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin_race_results.actions.close')),
                Action::make('send')
                    ->label(fn (array $record) => __('admin_race_results.actions.'.($record['alert']?->sent_at ? 'resend' : 'send')))
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (array $record) => $record['results'])
                    ->modalHeading(fn (array $record) => $record['name'])
                    ->modalDescription(fn (array $record) => $record['alert']?->sent_at
                        ? __('admin_race_results.resend_warning', ['time' => $record['alert']->sent_at->timezone(config('racing.timezone'))->format('Y-m-d H:i')])
                        : null)
                    ->schema([
                        CheckboxList::make('recipients')
                            ->label(__('admin_race_results.recipients'))
                            ->options(fn () => $this->recipientOptions())
                            ->default(fn () => array_keys($this->recipientOptions()))
                            ->required(),
                    ])
                    ->action(fn (array $record, array $data) => $this->sendOne($record, array_values($data['recipients']))),
                Action::make('open')
                    ->label(__('admin_race_results.actions.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (array $record) => RaceResultMessage::pageUrl($record['id']), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading(fn () => __('admin_race_results.'.($this->unavailable ? 'unavailable' : 'empty')))
            ->emptyStateIcon(fn () => $this->unavailable ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-calendar');
    }

    /** @return array<string, string> the race days up to today, newest first (this season, else the latest one run) */
    private function days(): array
    {
        if ($this->days !== null) {
            return $this->days;
        }

        $today = RaceResultAlerts::today();

        try {
            $racing = app(RacingClient::class);
            $days = $racing->raceDaysOfSeasonOf($today);
            $seasons = $racing->seasons();

            if (array_filter(array_keys($days), fn (string $d) => $d <= $today) === [] && $seasons !== []) {
                $days = $racing->raceDays($seasons[0]) + $days;
            }
        } catch (RacingUnavailableException $e) {
            report($e);
            $days = [];
        }

        $days = array_filter($days, fn (string $date) => $date <= $today, ARRAY_FILTER_USE_KEY);
        krsort($days);

        return $this->days = collect($days)
            ->mapWithKeys(fn (?string $meeting, string $date) => [$date => implode(' · ', array_filter([$date, $meeting]))])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function rows(?string $date): array
    {
        $date ??= array_key_first($this->days());

        if ($date === null || ! isset($this->days()[$date])) {
            return [];
        }

        try {
            $races = app(RaceResultAlerts::class)->races($date);
        } catch (RacingUnavailableException $e) {
            report($e);
            $this->unavailable = true;

            return [];
        }

        $this->unavailable = false;

        return array_map(fn (array $race, int $i) => $race + [
            ArrayRecord::getKeyName() => (string) $race['id'],
            'no' => $i + 1,
            'date' => $date,
        ], $races, array_keys($races));
    }

    private function status(?RaceResultAlert $alert): string
    {
        $time = fn ($at) => $at->timezone(config('racing.timezone'))->format('H:i');

        return match (true) {
            $alert?->sent_at === null && $alert?->results_seen_at !== null => __('admin_race_results.status.waiting'),
            $alert?->sent_at === null => __('admin_race_results.status.not_sent'),
            $alert->mode === 'auto' => __('admin_race_results.status.auto', ['time' => $time($alert->sent_at)]),
            $alert->sender !== null => __('admin_race_results.status.manual', ['name' => $alert->sender->name, 'time' => $time($alert->sent_at)]),
            default => __('admin_race_results.status.manual_unknown', ['time' => $time($alert->sent_at)]),
        };
    }

    private function statusDetail(?RaceResultAlert $alert): ?string
    {
        if ($alert?->sent_at === null) {
            return null;
        }

        return implode(' · ', array_filter([
            __('admin_race_results.status.recipients', ['count' => $alert->recipients]),
            $alert->video_sent_at && $alert->video_sent_at->gt($alert->sent_at->addMinute())
                ? __('admin_race_results.status.replay_followed', ['time' => $alert->video_sent_at->timezone(config('racing.timezone'))->format('H:i')])
                : null,
        ]));
    }

    /** @return array<string, string> normalized number => "name (number)" */
    private function recipientOptions(): array
    {
        return collect(WhatsAppSettings::recipientRows())
            ->mapWithKeys(function (array $row) {
                $phone = PhoneNumber::normalize($row['phone'] ?? null);

                return $phone ? [$phone => trim(($row['name'] ?? '') !== '' ? "{$row['name']} ({$phone})" : $phone)] : [];
            })
            ->all();
    }

    private function previewHtml(array $record): HtmlString
    {
        try {
            $message = app(RaceResultAlerts::class)->preview($record['id'], $record['video']);
        } catch (RacingUnavailableException $e) {
            report($e);
            $message = __('admin_race_results.unavailable');
        }

        return new HtmlString('<div dir="auto" class="whitespace-pre-wrap rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5">'
            .e($message ?? __('admin_race_results.no_results')).'</div>');
    }

    /** @param  list<string>  $recipients */
    private function sendOne(array $record, array $recipients): void
    {
        try {
            $count = app(RaceResultAlerts::class)->send($record['id'], $record['date'], $recipients, 'manual', auth()->user(), $record['video']);
        } catch (RacingUnavailableException $e) {
            report($e);
            Notification::make()->danger()->title(__('admin_race_results.unavailable'))->send();

            return;
        }

        $count === null
            ? Notification::make()->warning()->title(__('admin_race_results.no_results'))->send()
            : Notification::make()->success()->title(__('admin_race_results.sent', ['count' => $count]))->send();
    }

    private function sendAll(): void
    {
        $alerts = app(RaceResultAlerts::class);
        $sent = 0;

        try {
            foreach ($this->getTableRecords() as $record) {
                if ($record['results'] && $record['alert']?->sent_at === null
                    && $alerts->send($record['id'], $record['date'], null, 'manual', auth()->user(), $record['video']) !== null) {
                    $sent++;
                }
            }
        } catch (RacingUnavailableException $e) {
            report($e);
            Notification::make()->danger()->title(__('admin_race_results.unavailable'))->send();

            return;
        }

        $sent === 0
            ? Notification::make()->info()->title(__('admin_race_results.nothing_to_send'))->send()
            : Notification::make()->success()->title(__('admin_race_results.sent_all', ['count' => $sent]))->send();
    }
}
