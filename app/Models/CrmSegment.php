<?php

namespace App\Models;

use App\Support\MobileApp\MobileProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A saved group of customers (admin → CRM → Segments): what they did in the app (an event at least
 * N times in the last D days, or not at all) and who they are (gender, age, phone country, country and region, where the account
 * was made, marketing consent, how complete the profile is). Worked out when used, so it is always
 * current. A push campaign with the "segment" audience goes to their signed-in phones.
 */
class CrmSegment extends Model
{
    public const SOURCES = ['web', 'app'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(PushCampaign::class);
    }

    /** The customers in it right now. */
    public function customers(): Builder
    {
        return self::queryFor((array) $this->rules);
    }

    /**
     * @param  array{did?: ?string, min_count?: ?int, within_days?: ?int, did_not?: ?string, not_within_days?: ?int, gender?: ?string, countries?: ?array, profile_countries?: ?array, regions?: ?array, age_min?: ?int, age_max?: ?int, source?: ?string, marketing_only?: ?bool, min_completion?: ?int}  $rules
     */
    public static function queryFor(array $rules): Builder
    {
        $did = $rules['did'] ?? null;
        $didNot = $rules['did_not'] ?? null;
        $ageMin = filled($rules['age_min'] ?? null) ? (int) $rules['age_min'] : null;
        $ageMax = filled($rules['age_max'] ?? null) ? (int) $rules['age_max'] : null;

        return Customer::query()
            ->when($did, fn (Builder $q) => $q->whereIn('id', AppEvent::query()
                ->select('customer_id')
                ->where('name', $did)
                ->whereNotNull('customer_id')
                ->where('occurred_at', '>=', now()->subDays(max(1, (int) ($rules['within_days'] ?? 30))))
                ->groupBy('customer_id')
                ->havingRaw('count(*) >= ?', [max(1, (int) ($rules['min_count'] ?? 1))])))
            ->when($didNot, fn (Builder $q) => $q->whereNotIn('id', AppEvent::query()
                ->select('customer_id')
                ->where('name', $didNot)
                ->whereNotNull('customer_id')
                ->where('occurred_at', '>=', now()->subDays(max(1, (int) ($rules['not_within_days'] ?? 30))))))
            ->when($rules['gender'] ?? null, fn (Builder $q, string $gender) => $q->where('gender', $gender))
            ->when(! empty($rules['countries']), fn (Builder $q) => $q->whereIn('country', $rules['countries']))
            // where they live, from the profile (the phone's country above is the sign-in's)
            ->when(! empty($rules['profile_countries']), fn (Builder $q) => $q->whereIn('country_id', $rules['profile_countries']))
            ->when(! empty($rules['regions']), fn (Builder $q) => $q->whereIn('region_id', $rules['regions']))
            // age in whole years: born on or before today minus age_min, after today minus (age_max + 1)
            ->when($ageMin !== null, fn (Builder $q) => $q->whereDate('date_of_birth', '<=', now()->subYears($ageMin)->toDateString()))
            ->when($ageMax !== null, fn (Builder $q) => $q->whereDate('date_of_birth', '>', now()->subYears($ageMax + 1)->toDateString()))
            ->when($rules['source'] ?? null, fn (Builder $q, string $source) => $q->where('source', $source))
            ->when($rules['marketing_only'] ?? false, fn (Builder $q) => $q->where('marketing_opt_in', true))
            ->when(filled($rules['min_completion'] ?? null), fn (Builder $q) => $q->where(DB::raw(self::completionSql()), '>=', (int) $rules['min_completion']));
    }

    /** Customer::profileCompletion() in SQL (0–100 over the shown profile fields), for filters. */
    public static function completionSql(): string
    {
        $fields = MobileProfile::completionColumns();
        $filled = collect($fields)
            ->map(fn (string $field) => in_array($field, ['date_of_birth', 'country_id', 'region_id'], true)
                ? "(case when {$field} is not null then 1 else 0 end)"
                : "(case when {$field} is not null and {$field} <> '' then 1 else 0 end)")
            ->implode(' + ');

        return '(('.$filled.') * 100 / '.count($fields).')';
    }
}
