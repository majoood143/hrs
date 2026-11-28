@php use App\Models\GameAttempt; @endphp
@if($rows === [])
    <p class="text-sm text-gray-500">{{ __('games.admin.leaderboard_empty') }}</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-start text-gray-500">
                <th class="py-1 text-start">{{ __('games.admin.fields.rank') }}</th>
                <th class="py-1 text-start">{{ __('games.admin.fields.customer') }}</th>
                <th class="py-1 text-start">{{ __('games.admin.fields.position') }}</th>
                <th class="py-1 text-start">{{ __('games.admin.fields.time') }}</th>
                <th class="py-1 text-start">{{ __('games.admin.fields.prize') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="border-t border-gray-200 dark:border-white/10">
                    <td class="py-1.5 font-semibold">{{ $row['rank'] }}</td>
                    <td class="py-1.5">{{ $row['name'] ?: '—' }} <span class="text-gray-500" dir="ltr">+{{ $row['phone'] }}</span></td>
                    <td class="py-1.5">{{ $row['position'] }}</td>
                    <td class="py-1.5 font-mono" dir="ltr">{{ GameAttempt::formatTime($row['race_time_ms']) }}</td>
                    <td class="py-1.5">{{ $prizes->get($row['rank'])?->title ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
