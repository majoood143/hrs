{{-- A pattern on its own part, for the patterns table. --}}
@php
    $record = $getRecord();
@endphp

<div style="--silks-{{ $record->area }}-base: #1F4FBF; --silks-{{ $record->area }}-accent: #F7D417; --silks-cap-base: #1F4FBF; padding: 0.25rem 0;">
    <x-silks.swatch :area="$record->area" :svg="$record->svg" :uid="'silks-thumb-'.$record->id" style="display: block; height: 3rem; width: auto;" />
</div>
