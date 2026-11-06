<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    private static bool $muted = false;

    /**
     * @var list<string>
     */
    private const IGNORED_ATTRIBUTES = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'last_login_at',
    ];

    /**
     * Execute a callback without triggering observer audit logs.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    public function created(Model $model): void
    {
        if (self::$muted) {
            return;
        }

        $attributes = array_diff_key(
            $model->getAttributes(),
            array_flip(self::IGNORED_ATTRIBUTES),
        );

        $this->record($model, 'created', null, $attributes);
    }

    public function updated(Model $model): void
    {
        if (self::$muted) {
            return;
        }

        $dirty = array_diff_key(
            $model->getDirty(),
            array_flip(self::IGNORED_ATTRIBUTES),
        );

        if ($dirty === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach (array_keys($dirty) as $attribute) {
            $oldValues[$attribute] = $model->getOriginal($attribute);
            $newValues[$attribute] = $model->getAttribute($attribute);
        }

        $this->record($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        if (self::$muted) {
            return;
        }

        $attributes = array_diff_key(
            $model->getOriginal(),
            array_flip(self::IGNORED_ATTRIBUTES),
        );

        $this->record($model, 'deleted', $attributes, null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(
        Model $model,
        string $event,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        $entityType = strtolower(class_basename($model));

        AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'action' => "{$entityType}.{$event}",
            'entity_type' => $entityType,
            'entity_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
