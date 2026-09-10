<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Seed the transaction/service statuses table.
     */
    public function run(): void
    {
        $statuses = [
            ['en_name' => 'Pending', 'ar_name' => 'قيد الانتظار'],
            ['en_name' => 'Approved', 'ar_name' => 'موافق عليه'],
            ['en_name' => 'In Progress', 'ar_name' => 'قيد التنفيذ'],
            ['en_name' => 'Completed', 'ar_name' => 'مكتمل'],
            ['en_name' => 'Rejected', 'ar_name' => 'مرفوض'],
            ['en_name' => 'Cancelled', 'ar_name' => 'ملغى'],
        ];

        foreach ($statuses as $status) {
            Status::query()->updateOrCreate(
                ['en_name' => $status['en_name']],
                $status
            );
        }
    }
}
