<?php

namespace Tests\Feature\Games;

use App\Filament\Pages\GameSounds;
use App\Support\Games\CommentaryClips;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** Admin → Games → Commentator sounds: clips uploaded per moment and language, then served to the game. */
class GameSoundsTest extends TestCase
{
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames();
        foreach (['2026_09_07_130009_create_site_settings_table', '2026_09_07_200624_add_managed_by_to_site_settings_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
        $this->withoutVite();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_game_gets_no_clips_until_some_are_uploaded(): void
    {
        $this->getJson('/game-sounds')->assertOk()->assertExactJson(['clips' => []]);
    }

    public function test_an_admin_uploads_clips_and_the_game_gets_their_urls(): void
    {
        $this->assertFalse(GameSounds::canAccess());
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        }, 'web');
        Auth::shouldUse('web');

        Livewire::test(GameSounds::class)
            ->assertSee(__('games.admin.sounds.moments.fourHundred'))
            ->set('data.clips.off.ar', [UploadedFile::fake()->create('off-ar.mp3', 40, 'audio/mpeg')])
            ->set('data.clips.playerWins.en', [UploadedFile::fake()->create('win.mp3', 40, 'audio/mpeg')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('games.admin.sounds.saved'));

        $clips = $this->getJson('/game-sounds')->assertOk()->json('clips');
        $this->assertSame(['off', 'playerWins'], array_keys($clips));
        $this->assertSame(['ar'], array_keys($clips['off']));
        $this->assertStringContainsString('games/commentary/', $clips['playerWins']['en']);
        Storage::disk('public')->assertExists(CommentaryClips::paths()['off']['ar']);

        // a text file is refused
        Livewire::test(GameSounds::class)
            ->set('data.clips.halfway.en', [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
            ->call('save')
            ->assertHasErrors();
    }
}
