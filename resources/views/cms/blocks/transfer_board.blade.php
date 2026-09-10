@php
    $heading = \App\Support\Localized::value($data, 'heading') ?: __('transportation.board_title');
    $subheading = \App\Support\Localized::value($data, 'subheading');
@endphp

@include('site.transfer-board._listing', ['heading' => $heading, 'subheading' => $subheading])
