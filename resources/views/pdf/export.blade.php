@php
    $align = $rtl ? 'right' : 'left';
    // numbers, dates and codes keep their left-to-right order inside an Arabic document
    $cell = function (?string $text) use ($rtl, $d): string {
        $text = trim((string) $text);
        $html = ($rtl && $text !== '' && ! preg_match('/\p{Arabic}/u', $text))
            ? '<span dir="ltr">'.e($text).'</span>'
            : $d($text)->toHtml();

        return nl2br($html, false);
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        body { font-family: cairo; color: #333333; direction: {{ $rtl ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 18pt; color: #000000; margin: 1mm 0 0 0; }
        .subtitle { font-size: 11pt; color: #333333; margin: 0; }
        .brand { width: 100%; border-bottom: 0.6mm solid #000000; margin-bottom: 4mm; }
        .brand td { padding: 0 0 2mm 0; vertical-align: middle; text-align: {{ $align }}; }
        .site { font-size: 14pt; font-weight: bold; color: #000000; }
        table.facts { margin-bottom: 4mm; }
        table.facts td { padding: 0.5mm 4mm 0.5mm 0; vertical-align: top; text-align: {{ $align }}; font-size: 8.5pt; }
        table.facts td.label { color: #666666; }
        table.grid { border-collapse: collapse; width: 100%; }
        table.grid th { background-color: #e6e6e6; color: #000000; text-align: {{ $align }}; padding: 1.4mm 1.6mm; border-bottom: 0.4mm solid #888888; }
        table.grid td { padding: 1.2mm 1.6mm; border-bottom: 0.2mm solid #dddddd; vertical-align: top; text-align: {{ $align }}; }
        table.grid tr.even td { background-color: #f7f7f7; }
        table.details td.label { width: 32%; color: #555555; }
        table.details tr.section td { font-weight: bold; color: #000000; background-color: #e6e6e6; border-bottom: 0.4mm solid #888888; padding-top: 2mm; }
        .note { margin-top: 4mm; padding: 2.5mm; border: 0.3mm dashed #aaaaaa; font-size: 8pt; color: #555555; }
        .empty { padding: 3mm; text-align: center; color: #666666; border: 0.3mm dashed #aaaaaa; }
    </style>
</head>
<body>
    <table class="brand"><tr><td>
        @if($logo)
            <img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}mm" height="{{ $logo['height'] }}mm" alt="{{ $siteName }}">
        @else
            <div class="site">{{ $siteName }}</div>
        @endif
        <h1>{{ $d($document->title) }}</h1>
        @if(filled($document->subtitle))
            <p class="subtitle">{{ $d($document->subtitle) }}</p>
        @endif
    </td></tr></table>

    @if($document->facts)
        <table class="facts">
            @foreach($document->facts as $label => $value)
                <tr><td class="label">{{ $label }}</td><td>{!! $cell($value) !!}</td></tr>
            @endforeach
        </table>
    @endif

    @if($rows === [])
        <p class="empty">{{ __('admin_export.empty') }}</p>
    @elseif($document->layout === \App\Services\Exports\ExportDocument::TABLE)
        <table class="grid">
            <thead>
                <tr>
                    @foreach($document->headings as $heading)
                        <th>{{ $d($heading) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr @class(['even' => $loop->even])>
                        @foreach($row as $value)
                            <td>{!! $cell(\Illuminate\Support\Str::limit($value, 400)) !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($truncated)
            <div class="note">{{ __('admin_export.truncated', ['shown' => count($rows), 'total' => $document->total]) }}</div>
        @endif
    @else
        <table class="grid details">
            @foreach($rows as $row)
                @if(isset($row['heading']))
                    <tr class="section"><td colspan="2">{{ $d($row['heading']) }}</td></tr>
                @else
                    <tr>
                        <td class="label">{{ $d($row['label']) }}</td>
                        <td>{!! $cell($row['value']) !!}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif
</body>
</html>
