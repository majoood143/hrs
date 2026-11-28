<?php

namespace App\Http\Resources\V1\Owner;

use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class OwnerExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** @example 2026-11-20 */
            'date' => $this->spent_on->toDateString(),
            'amount' => new MoneyResource($this->amount),
            'category' => OptionResource::of((string) $this->expense_category_id, (string) $this->category?->name),
            /** @var array{value: string, label: string}|null */
            'horse' => $this->horse ? OptionResource::of((string) $this->stable_horse_id, $this->horse->name) : null,
            /** @var string|null */
            'vendor' => $this->vendor,
            /** @var string|null */
            'note' => $this->note,
            /** A signed link, good for 30 minutes. @var string|null */
            'receipt_url' => $this->receiptUrl(),
            /** image | pdf, or null without a receipt. @var string|null */
            'receipt_type' => $this->receipt ? (str_ends_with(strtolower($this->receipt), '.pdf') ? 'pdf' : 'image') : null,
        ];
    }
}
