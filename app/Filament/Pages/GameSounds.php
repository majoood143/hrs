<?php

namespace App\Filament\Pages;

use App\Support\Games\CommentaryClips;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Admin → Games → Commentator sounds: the recorded clips the horse racing game plays (CommentaryClips). */
class GameSounds extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-speaker-wave';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.game-sounds';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Update:GameCampaign') ?? false;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('games.admin.navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('games.admin.sounds.title');
    }

    public function getTitle(): string
    {
        return __('games.admin.sounds.title');
    }

    public function getSubheading(): ?string
    {
        return __('games.admin.sounds.intro');
    }

    public function mount(): void
    {
        $this->form->fill(['clips' => CommentaryClips::paths()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(array_map(fn (string $moment) => Section::make(__("games.admin.sounds.moments.{$moment}"))
                ->description(__("games.admin.sounds.examples.{$moment}"))
                ->columns(2)
                ->compact()
                ->schema(array_map(fn (string $language) => FileUpload::make("clips.{$moment}.{$language}")
                    ->label(__("games.admin.sounds.languages.{$language}"))
                    ->disk('public')
                    ->directory(CommentaryClips::DIRECTORY)
                    ->visibility('public')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav'])
                    ->maxSize(2048), CommentaryClips::LANGUAGES)), CommentaryClips::MOMENTS));
    }

    public function save(): void
    {
        CommentaryClips::save($this->form->getState()['clips'] ?? []);

        Notification::make()->title(__('games.admin.sounds.saved'))->success()->send();
    }
}
