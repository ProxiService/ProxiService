<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('dispute_status', 20)->nullable()->index();
            $table->text('dispute_reason')->nullable();
            $table->foreignId('disputed_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_resolution')->nullable();
            $table->foreignId('dispute_resolved_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('dispute_resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropForeign(['disputed_by_user_id']);
            $table->dropForeign(['dispute_resolved_by_user_id']);
            $table->dropIndex(['dispute_status']);
            $table->dropColumn([
                'cancellation_reason',
                'cancelled_by_user_id',
                'cancelled_at',
                'dispute_status',
                'dispute_reason',
                'disputed_by_user_id',
                'disputed_at',
                'dispute_resolution',
                'dispute_resolved_by_user_id',
                'dispute_resolved_at',
            ]);
        });
    }
};
