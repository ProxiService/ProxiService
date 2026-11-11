<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('provider_profile_id')->nullable()->change();
            $table->decimal('urgent_latitude', 10, 7)->nullable();
            $table->decimal('urgent_longitude', 10, 7)->nullable();
            $table->timestamp('urgent_expires_at')->nullable();
            $table->timestamp('urgent_expanded_at')->nullable();
            $table->string('urgent_dispatch_status', 30)->nullable();
        });

        Schema::create('urgent_service_request_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('matched_radius_km');
            $table->timestamp('notified_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['service_request_id', 'provider_profile_id'], 'urgent_match_unique');
            $table->index(['provider_profile_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_service_request_matches');
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'urgent_latitude', 'urgent_longitude', 'urgent_expires_at',
                'urgent_expanded_at', 'urgent_dispatch_status',
            ]);
        });
    }
};
