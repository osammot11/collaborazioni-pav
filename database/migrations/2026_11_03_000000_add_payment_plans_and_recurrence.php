<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->decimal('recurring_amount', 12, 2)->default(0);
            $table->string('recurring_frequency')->default('mensile');
            $table->date('next_renewal_date')->nullable()->index();
            $table->decimal('payment_total', 12, 2)->nullable();
            $table->string('payment_scope')->nullable();
            $table->json('payment_entries')->nullable();
            $table->json('payment_schedule')->nullable();
            $table->date('next_payment_due_on')->nullable()->index();
        });
        // Preserve existing semantics. Annual amounts previously divided by 12 must be corrected explicitly.
        DB::table('collaborations')->update(['recurring_amount' => DB::raw('monthly_revenue'), 'next_payment_due_on' => DB::raw('payment_deadline')]);
        Schema::create('finance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_events');
        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropIndex(['next_renewal_date']);
            $table->dropIndex(['next_payment_due_on']);
            $table->dropColumn(['recurring_amount', 'recurring_frequency', 'next_renewal_date',
                'payment_total', 'payment_scope', 'payment_entries', 'payment_schedule', 'next_payment_due_on']);
        });
    }
};
