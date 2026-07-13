<?php

namespace App\Models\Concerns;

/**
 * When a model's slug changes, persist the previous slug into its history table
 * so old shared URLs can 301-redirect to the current one. The consuming model
 * must implement slugHistories() and expose a `slugHistoryForeignKey`.
 */
trait RecordsSlugHistory
{
    public static function bootRecordsSlugHistory(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty('slug')) {
                $original = $model->getOriginal('slug');
                if ($original && $original !== $model->slug) {
                    $model->slugHistories()->firstOrCreate(['old_slug' => $original]);
                }
            }
        });
    }
}
