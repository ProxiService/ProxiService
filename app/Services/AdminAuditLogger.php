<?php

namespace App\Services;

use App\Models\AdminAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(Request $request, string $action, Model $target, ?array $before, ?array $after): void
    {
        AdminAudit::query()->create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $target::class,
            'auditable_id' => $target->getKey(),
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
        ]);
    }
}
