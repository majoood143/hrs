{{-- $table: {headers: list<string>, rows: list<list<{text, link, title}>>} --}}
<div class="overflow-x-auto rounded-2xl border border-warm-200/70 bg-white shadow-sm shadow-warm-900/5">
    <table class="min-w-full text-sm">
        <thead class="bg-warm-50 text-xs font-semibold uppercase tracking-wide text-warm-700">
            <tr>
                @foreach($table['headers'] as $header)
                    <th scope="col" class="px-4 py-3 text-start">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-warm-100 text-warm-900">
            @foreach($table['rows'] as $row)
                <tr class="hover:bg-warm-50/60">
                    @foreach($row as $cell)
                        <td class="whitespace-nowrap px-4 py-3" @if($cell['title'] ?? null) title="{{ $cell['title'] }}" @endif>
                            <x-racing.cell :cell="$cell" />
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
