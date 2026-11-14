<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes created/updated/deleted events to audit_logs.
 * Attributes listed in $auditExclude (and hidden attributes) are never logged.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => Audit::log(class_basename($m).'.created', $m, ['attributes' => $m->auditPayload($m->getAttributes())]));
        static::updated(function (Model $m) {
            $changes = $m->auditPayload($m->getChanges());
            unset($changes['updated_at']);
            if ($changes !== []) {
                Audit::log(class_basename($m).'.updated', $m, ['changes' => $changes]);
            }
        });
        static::deleted(fn (Model $m) => Audit::log(class_basename($m).'.deleted', $m));
    }

    /** @param array<string, mixed> $attributes */
    public function auditPayload(array $attributes): array
    {
        $exclude = array_merge($this->getHidden(), $this->auditExclude ?? [], ['created_at', 'updated_at']);

        return collect($attributes)
            ->except($exclude)
            ->map(fn ($v) => is_string($v) && strlen($v) > 500 ? substr($v, 0, 500).'…' : $v)
            ->all();
    }
}
