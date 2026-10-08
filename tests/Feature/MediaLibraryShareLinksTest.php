<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaLibraryResource\Pages\ListMediaLibraryFiles;
use App\Filament\Resources\MediaLibraryResource\Pages\ManageMediaLibraryFiles;
use App\Livewire\MediaExplorer;
use App\Support\MediaLinks;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\MakesMediaLibraries;
use Tests\TestCase;

class MediaLibraryShareLinksTest extends TestCase
{
    use MakesMediaLibraries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMediaLibraries();

        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->stubUser(1));
    }

    public function test_a_public_file_links_to_its_storage_address(): void
    {
        [, $media] = $this->libraryWithFile('public');

        $this->assertSame("https://muhraequine.om/storage/{$media->id}/change-ownership.pdf", MediaLinks::url($media));
    }

    public function test_a_file_on_a_private_disk_has_no_link(): void
    {
        [, $media] = $this->libraryWithFile('local');

        $this->assertNull(MediaLinks::url($media));
        $this->assertNull(MediaLinks::payload($media)['url']);
    }

    public function test_the_explorer_shows_the_share_bar_with_every_file_link(): void
    {
        [$library, $media] = $this->libraryWithFile('public');

        $component = Livewire::test(MediaExplorer::class, [
            'scopeKey' => $library->fileExplorerScopeKey(),
            'rootFolderId' => $library->fileExplorerRootFolderId(),
        ]);

        $files = $component->instance()->shareableFiles();

        $this->assertSame("https://muhraequine.om/storage/{$media->id}/change-ownership.pdf", $files[$media->id]['url']);
        $this->assertSame('change-ownership.pdf', $files[$media->id]['name']);
        $this->assertFalse($files[$media->id]['image']);

        $component
            ->assertSee(__('admin_media_library.share.copy'))
            ->assertSee(__('admin_media_library.share.public_notice'))
            ->assertSee('fe-finder'); // the plugin's own explorer is still rendered
    }

    public function test_the_explorer_page_renders_the_media_explorer(): void
    {
        [$library] = $this->libraryWithFile('public');

        Livewire::test(ManageMediaLibraryFiles::class, ['record' => $library->getKey()])
            ->assertSeeLivewire(MediaExplorer::class);
    }

    public function test_the_files_table_has_a_copyable_link_column_and_link_actions(): void
    {
        [$library, $media] = $this->libraryWithFile('public');

        Livewire::test(ListMediaLibraryFiles::class, ['record' => $library->getKey()])
            ->assertTableColumnExists('public_link')
            ->assertTableColumnStateSet('public_link', "https://muhraequine.om/storage/{$media->id}/change-ownership.pdf", $media)
            ->assertTableActionVisible('copyLink', $media)
            ->assertTableActionVisible('openLink', $media);
    }

    public function test_link_actions_are_hidden_for_a_private_file(): void
    {
        [$library, $media] = $this->libraryWithFile('local');

        Livewire::test(ListMediaLibraryFiles::class, ['record' => $library->getKey()])
            ->assertTableActionHidden('copyLink', $media)
            ->assertTableActionHidden('openLink', $media);
    }
}
