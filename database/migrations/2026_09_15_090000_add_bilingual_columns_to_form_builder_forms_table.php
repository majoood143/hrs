<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts name/description/submit_label/success_message on the forms table
 * to JSON so they can hold {"en": ..., "ar": ...} via Spatie\Translatable.
 * Existing scalar values are wrapped as {"en": <old value>} first, so no
 * data is lost.
 */
return new class extends Migration
{
    protected string $table;

    public function __construct()
    {
        $this->table = config('packstub-form-builder.tables.forms', 'form_builder_forms');
    }

    public function up(): void
    {
        foreach (DB::table($this->table)->select('id', 'name', 'description', 'submit_label', 'success_message')->get() as $row) {
            DB::table($this->table)->where('id', $row->id)->update([
                'name' => json_encode(['en' => $row->name]),
                'description' => $row->description === null ? null : json_encode(['en' => $row->description]),
                'submit_label' => $row->submit_label === null ? null : json_encode(['en' => $row->submit_label]),
                'success_message' => $row->success_message === null ? null : json_encode(['en' => $row->success_message]),
            ]);
        }

        DB::statement("ALTER TABLE `{$this->table}` MODIFY `name` JSON NOT NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `description` JSON NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `submit_label` JSON NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `success_message` JSON NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `name` VARCHAR(255) NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `description` TEXT NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `submit_label` VARCHAR(255) NULL");
        DB::statement("ALTER TABLE `{$this->table}` MODIFY `success_message` TEXT NULL");

        foreach (DB::table($this->table)->select('id', 'name', 'description', 'submit_label', 'success_message')->get() as $row) {
            DB::table($this->table)->where('id', $row->id)->update([
                'name' => data_get(json_decode((string) $row->name, true), 'en'),
                'description' => $row->description === null ? null : data_get(json_decode($row->description, true), 'en'),
                'submit_label' => $row->submit_label === null ? null : data_get(json_decode($row->submit_label, true), 'en'),
                'success_message' => $row->success_message === null ? null : data_get(json_decode($row->success_message, true), 'en'),
            ]);
        }

        DB::statement("ALTER TABLE `{$this->table}` MODIFY `name` VARCHAR(255) NOT NULL");
    }
};
