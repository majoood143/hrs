{{-- The plugin's explorer, untouched, plus our share bar underneath. --}}
<div>
    @include('filament-file-explorer::livewire.file-explorer')

    @include('filament.media-library.share-bar', ['shareFiles' => $this->shareableFiles()])
</div>
