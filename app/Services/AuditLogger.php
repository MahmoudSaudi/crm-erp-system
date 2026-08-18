<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip' => Request::ip(),
            'user_agent' => mb_substr(Request::userAgent() ?? '', 0, 255),
        ]);
    }
}
