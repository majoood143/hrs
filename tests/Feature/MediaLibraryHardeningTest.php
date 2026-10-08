<?php

namespace Tests\Feature;

use App\Livewire\MediaExplorer;
use App\Models\MediaLibrary;
use App\Support\MediaLibraryAuthorizer;
use Ardavan\FilamentFileExplorer\Contracts\FileExplorerAuthorizer;
use Ardavan\FilamentFileExplorer\Models\Folder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\MakesMediaLibraries;
use Tests\TestCase;

class MediaLibraryHardeningTest extends TestCase
{
    use MakesMediaLibraries;

    private const ADMIN = 1;

    private const VIEWER = 2;   // may view libraries, not change them

    private const OUTSIDER = 3; // e.g. a stable owner: no Media Library permissions

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMediaLibraries();
        Storage::fake('public', ['url' => 'https://muhraequine.om/storage', 'visibility' => 'public']);

        Gate::before(fn ($user, string $ability) => match ((int) $user->getAuthIdentifier()) {
            self::ADMIN => true,
            self::VIEWER => in_array($ability, ['view', 'View:MediaLibrary', 'viewAny', 'ViewAny:MediaLibrary'], true) ? true : null,
            default => null,
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_app_uses_its_own_authorizer(): void
    {
        $this->assertInstanceOf(MediaLibraryAuthorizer::class, app(FileExplorerAuthorizer::class));
    }

    public function test_abilities_follow_the_media_library_permissions(): void
    {
        [$library] = $this->libraryWithFile();
        $root = $library->fileExplorerRootFolderId();

        $this->actingAs($this->stubUser(self::ADMIN));
        $this->assertTrue(app(MediaLibraryAuthorizer::class)->abilities('', $root)['upload']);

        $this->actingAs($this->stubUser(self::VIEWER));
        $viewer = app(MediaLibraryAuthorizer::class);
        $this->assertTrue($viewer->canAccess('', $root));
        $this->assertTrue($viewer->abilities('', $root)['download']);
        $this->assertFalse($viewer->abilities('', $root)['upload']);
        $this->assertFalse($viewer->abilities('', $root)['delete']);

        $this->actingAs($this->stubUser(self::OUTSIDER));
        $this->assertFalse(app(MediaLibraryAuthorizer::class)->canAccess('', $root));
    }

    public function test_a_folder_that_belongs_to_no_library_is_refused(): void
    {
        $this->actingAs($this->stubUser(self::ADMIN));
        $stray = Folder::query()->create(['name' => 'Stray', 'slug' => 'stray', 'parent_id' => null]);

        $this->assertFalse(app(MediaLibraryAuthorizer::class)->canAccess('', $stray->id));
    }

    public function test_the_plugin_download_route_refuses_users_without_permission(): void
    {
        [$library, $media] = $this->libraryWithFile();
        Storage::disk('public')->put("{$media->id}/change-ownership.pdf", '%PDF-1.4 test');
        $url = route('filament-file-explorer.media.show', ['scopeKey' => $library->fileExplorerScopeKey(), 'media' => $media->id, 'download' => 1]);

        $this->actingAs($this->stubUser(self::OUTSIDER))->get($url)->assertForbidden();

        $this->actingAs($this->stubUser(self::VIEWER))->get($url)->assertOk();
    }

    public function test_the_root_folder_takes_the_english_name_and_follows_renames(): void
    {
        app()->setLocale('ar');
        $library = MediaLibrary::query()->create(['en_name' => 'Forms', 'ar_name' => 'النماذج']);

        $this->assertSame('Forms', $library->folder->name);

        $library->update(['en_name' => 'Official forms']);

        $this->assertSame('Official forms', $library->folder->fresh()->name);
    }

    public function test_deleting_a_library_deletes_its_folders_and_files(): void
    {
        [$library, $media] = $this->libraryWithFile();
        Storage::disk('public')->put("{$media->id}/change-ownership.pdf", '%PDF-1.4 test');
        $root = $library->fileExplorerRootFolderId();
        $child = Folder::query()->create(['name' => 'Old', 'slug' => 'old', 'parent_id' => $root]);
        $inner = $this->addFile($child->id, 'public', 'inner.pdf');
        [$other, $kept] = $this->libraryWithFile('public', 'kept.pdf');

        $this->assertSame(2, $library->fileCount());

        $library->delete();

        $this->assertNull(Folder::query()->find($root));
        $this->assertNull(Folder::query()->find($child->id));
        $this->assertNull(Media::query()->find($media->id));
        $this->assertNull(Media::query()->find($inner->id));
        Storage::disk('public')->assertMissing("{$media->id}/change-ownership.pdf");

        // another library is untouched
        $this->assertNotNull(Media::query()->find($kept->id));
        $this->assertSame(1, $other->fileCount());
    }

    public function test_the_upload_notification_is_translated_and_offers_the_link(): void
    {
        app()->setLocale('en');
        $this->actingAs($this->stubUser(self::ADMIN));
        $library = MediaLibrary::query()->create(['en_name' => 'Forms', 'ar_name' => 'النماذج']);

        Livewire::test(MediaExplorer::class, [
            'scopeKey' => $library->fileExplorerScopeKey(),
            'rootFolderId' => $library->fileExplorerRootFolderId(),
        ])->set('files', [UploadedFile::fake()->create('Change Ownership.pdf', 12, 'application/pdf')]);

        $media = Media::query()->latest('id')->firstOrFail();
        // Filament hands sent notifications over as "claimed" once the Livewire request ends
        $notification = collect([...session('filament.claimed_notifications', []), ...session('filament.notifications', [])])->last();

        $this->assertSame('1 file uploaded to "Forms".', $notification['body']);
        $this->assertStringNotContainsString('فایل', json_encode($notification, JSON_UNESCAPED_UNICODE));
        $this->assertSame(['copyLink', 'openLink'], array_column($notification['actions'], 'name'));
        $this->assertSame("https://muhraequine.om/storage/{$media->id}/change-ownership.pdf", $notification['actions'][1]['url']);
    }

    public function test_the_qr_code_is_drawn_only_for_files_of_the_open_library(): void
    {
        $this->actingAs($this->stubUser(self::ADMIN));
        [$library, $media] = $this->libraryWithFile();
        [, $foreign] = $this->libraryWithFile('public', 'elsewhere.pdf');

        $explorer = Livewire::test(MediaExplorer::class, [
            'scopeKey' => $library->fileExplorerScopeKey(),
            'rootFolderId' => $library->fileExplorerRootFolderId(),
        ])->instance();

        $this->assertStringStartsWith('<svg', (string) $explorer->qrSvg($media->id));
        $this->assertStringContainsString('<rect', (string) $explorer->qrSvg($media->id));
        $this->assertNull($explorer->qrSvg($foreign->id));
    }
}
