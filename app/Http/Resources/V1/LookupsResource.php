<?php

namespace App\Http\Resources\V1;

use App\Models\Center;
use App\Models\CenterService;
use App\Models\Clinic;
use App\Models\ClinicService;
use App\Models\Color;
use App\Models\Country;
use App\Models\EventCategory;
use App\Models\Gender;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\StableService;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the listing filters choose from, in the request's language, ordered like the website's filters.
 */
class LookupsResource extends JsonResource
{
    public function __construct()
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $countries = Country::query()->public()
            ->with('region.city')
            ->orderBy('en_name')
            ->get();

        return [
            /** Public countries → regions → cities (the website's location filter). */
            'countries' => CountryResource::collection($countries),
            /** Horse types (breed groups). */
            'types' => LookupResource::collection(Type::query()->orderBy('en_name')->get()),
            'genders' => LookupResource::collection(Gender::query()->orderBy('en_name')->get()),
            'colors' => ColorResource::collection(Color::query()->orderBy('en_name')->get()),
            'tool_categories' => OptionResource::collection(collect(ToolSalePost::CATEGORIES)
                ->map(fn (string $value) => ['value' => $value, 'label' => __('tools-for-sale.categories.'.$value)])),
            'tool_conditions' => OptionResource::collection(collect(ToolSalePost::CONDITIONS)
                ->map(fn (string $value) => ['value' => $value, 'label' => __('tools-for-sale.conditions.'.$value)])),
            /** Transfer board `type`: offer or request. */
            'transfer_types' => OptionResource::collection(collect(TransferPost::TYPES)
                ->map(fn (string $value) => ['value' => $value, 'label' => __('transportation.type_'.$value)])),
            'clinic_types' => self::types(Clinic::TYPES, 'clinics'),
            'center_types' => self::types(Center::TYPES, 'centers'),
            'shop_types' => self::types(Shop::TYPES, 'shops'),
            'stable_services' => ServiceResource::collection(StableService::query()->orderBy('en_name')->get()),
            'clinic_services' => ServiceResource::collection(ClinicService::query()->orderBy('en_name')->get()),
            'center_services' => ServiceResource::collection(CenterService::query()->orderBy('en_name')->get()),
            'shop_services' => ServiceResource::collection(ShopService::query()->orderBy('en_name')->get()),
            'event_categories' => EventCategoryResource::collection(EventCategory::query()->orderBy('order')->get()),
        ];
    }

    /**
     * @param  array<string, string>  $types
     */
    private static function types(array $types, string $section): AnonymousResourceCollection
    {
        return OptionResource::collection(collect(array_keys($types))
            ->map(fn (string $value) => ['value' => $value, 'label' => __("{$section}.types.{$value}")]));
    }
}
