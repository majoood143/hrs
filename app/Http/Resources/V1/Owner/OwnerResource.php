<?php

namespace App\Http\Resources\V1\Owner;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signed-in stable owner and the stables they run (owner or staff).
 *
 * @property-read User $resource
 */
class OwnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            /** @var string|null */
            'email' => $user->email,
            /** With the country code, digits only. @var string|null */
            'phone' => $user->phone,
            'stables' => OwnerStableResource::collection($user->stables()->orderBy('stables.id')->get()),
        ];
    }
}
