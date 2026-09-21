{{-- $table: {headers: list<string>, rows: list<list<{text, link, title}>>} --}}
<table class="grid" repeat_header="1">
    <thead>
        <tr>
            @foreach($table['headers'] as $header)
                <th>{{ $d($header) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($table['rows'] as $row)
            <tr>
                @foreach($row as $cell)
                    @php $href = \App\Support\RacingLinks::href($cell['link'] ?? null); @endphp
                    <td>@if($href)<a href="{{ $href }}">{{ $d($cell['text']) }}</a>@else{{ $d($cell['text']) }}@endif</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
