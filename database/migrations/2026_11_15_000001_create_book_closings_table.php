<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Each close or reopen of the books is a row (the latest one wins), so the history stays. */
    public function up(): void
    {
        Schema::create('book_closings', function (Blueprint $table) {
            $table->id();
            // the last closed day, a plain Y-m-d; null = nothing closed (a reopen of the first month)
            $table->string('closed_through', 10)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_closings');
    }
};
