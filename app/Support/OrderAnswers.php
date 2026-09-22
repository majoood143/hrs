<?php

namespace App\Support;

use App\Models\ServiceOrder;

/**
 * What the customer wrote in the form, for their own order page. Files are only named as uploaded:
 * the download links are for the reviewers.
 */
class OrderAnswers
{
    /** @return list<array{label: string, value: string}> */
    public static function for(ServiceOrder $order): array
    {
        $submission = $order->submission;

        if (! $submission) {
            return [];
        }

        return collect($submission->formatted())
            ->map(function (array $row, string $key) use ($submission): array {
                $isFile = $submission->form?->field($key)?->type::id() === 'file';

                return ['label' => $row['label'], 'value' => $isFile && $row['value'] !== '' ? __('account.file_uploaded') : $row['value']];
            })
            ->filter(fn (array $row) => $row['value'] !== '')
            ->values()
            ->all();
    }
}
