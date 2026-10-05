<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Template pesan promo yang bisa dipakai ulang di campaign builder. */
class WaCampaignTemplate extends Model
{
    protected $fillable = ['name', 'body', 'image_path', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
