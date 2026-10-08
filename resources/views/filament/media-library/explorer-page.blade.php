<x-filament-panels::page>
    <livewire:media-explorer
        :scope-key="$this->fileExplorerScopeKey()"
        :root-folder-id="$this->rootFolderId"
        :key="'media-explorer-'.$this->fileExplorerScopeKey().'-'.request()->integer('folder')"
    />
</x-filament-panels::page>
