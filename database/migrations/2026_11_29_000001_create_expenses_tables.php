<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses (General Settings → Modules → Expenses): our own running costs (stable_id null) and each
 * stable's, recorded by its owner. Categories are one list the admins keep; `scope` says who sees one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('scope')->default('both'); // both | platform | stable
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('stable_horse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->decimal('amount', 10, 3);
            $table->string('vendor')->nullable();
            $table->text('note')->nullable();
            $table->string('receipt')->nullable(); // on the private "local" disk
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['stable_id', 'spent_on']);
        });

        $now = now();
        DB::table('expense_categories')->insert(collect([
            ['Feed', 'الأعلاف', 'stable'],
            ['Vet', 'الطبيب البيطري', 'stable'],
            ['Farrier', 'البيطار', 'stable'],
            ['Salaries', 'الرواتب', 'both'],
            ['Rent', 'الإيجار', 'both'],
            ['Utilities', 'الخدمات (كهرباء وماء)', 'both'],
            ['Equipment', 'المعدات', 'both'],
            ['Transport', 'النقل', 'both'],
            ['Marketing', 'التسويق', 'both'],
            ['Bank and gateway fees', 'رسوم البنك وبوابة الدفع', 'both'],
            ['Other', 'أخرى', 'both'],
        ])->values()->map(fn (array $c, int $i) => [
            'en_name' => $c[0], 'ar_name' => $c[1], 'scope' => $c[2], 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
