<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Racing\RacingClient;
use App\Services\Racing\RacingSearchType;
use App\Services\Racing\RacingUnavailableException;
use App\Support\RacingSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class RacingController extends Controller
{
    public function __construct(private readonly RacingClient $racing)
    {
    }

    public function search(Request $request): View
    {
        $raw = $request->query('q');
        $query = is_string($raw) ? trim(preg_replace('/\s+/u', ' ', $raw) ?? '') : '';
        $type = RacingSearchType::fromInput($request->query('type'));
        $min = config('racing.min_query_length');

        $state = [
            'q' => $query,
            'type' => $type,
            'sections' => [],
            'error' => null,
            'unavailable' => false,
        ];

        if ($query !== '' && mb_strlen($query) < $min) {
            $state['error'] = __('racing.min_chars', ['min' => $min]);
        } elseif ($query !== '') {
            $query = mb_substr($query, 0, config('racing.max_query_length'));

            try {
                $state['sections'] = $this->sections($request, $query, $type);
            } catch (RacingUnavailableException $e) {
                report($e);
                $state['unavailable'] = true;
            }
        }

        return view('site.racing.search', $state + RacingSeo::for(__('racing.title')));
    }

    public function profile(string $entity, int $id): View
    {
        abort_unless(isset(RacingClient::ENTITIES[$entity]), 404);

        try {
            $profile = $this->racing->profile($entity, $id, app()->getLocale());
        } catch (RacingUnavailableException $e) {
            report($e);

            return view('site.racing.profile', [
                'entity' => $entity,
                'profile' => null,
                'unavailable' => true,
            ] + RacingSeo::for(__('racing.title')));
        }

        abort_if($profile === null, 404);

        return view('site.racing.profile', [
            'entity' => $entity,
            'id' => $id,
            'profile' => $profile,
            'unavailable' => false,
        ] + RacingSeo::for($profile['title'] . ' — ' . __('racing.title'), implode(' · ', array_filter([__('racing.profile_types.' . $entity), ...$profile['meta'], $profile['pedigree']]))));
    }

    /** Proxy for the source's /img/* files (it is http:// only). */
    public function image(Request $request): Response
    {
        $path = $request->query('p');
        $image = is_string($path) ? $this->racing->image($path) : null;

        abort_if($image === null, 404);

        return response($image['body'], 200, [
            'Content-Type' => $image['type'],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * One entry per result group: [group, headers, rows|paginator, total, more_url].
     * A specific type is paginated; "All" shows a short preview per group.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RacingUnavailableException
     */
    private function sections(Request $request, string $query, RacingSearchType $type): array
    {
        $groups = $this->racing->search($query, $type, app()->getLocale());
        $sections = [];

        foreach (['horse', 'owner', 'jockey', 'trainer'] as $group) {
            if ($type->group() !== null && $type->group() !== $group) {
                continue;
            }

            $data = $groups[$group] ?? null;

            if ($data === null) {
                continue;
            }

            $total = count($data['rows']);

            if ($type === RacingSearchType::All) {
                $rows = array_slice($data['rows'], 0, config('racing.all_preview'));
                $more = $total > count($rows)
                    ? route('racing.search', ['q' => $query, 'type' => RacingSearchType::fromGroup($group)->value])
                    : null;
            } else {
                $perPage = config('racing.per_page');
                $page = max(1, (int) $request->query('page', 1));

                $rows = new LengthAwarePaginator(
                    array_slice($data['rows'], ($page - 1) * $perPage, $perPage),
                    $total,
                    $perPage,
                    $page,
                    ['path' => route('racing.search'), 'query' => ['q' => $query, 'type' => $type->value]],
                );
                $more = null;
            }

            $sections[] = [
                'group' => $group,
                'headers' => $data['headers'],
                'rows' => $rows,
                'total' => $total,
                'more_url' => $more,
            ];
        }

        return $sections;
    }
}
