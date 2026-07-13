<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationAttachment extends Model
{
    protected $fillable = ['quotation_id', 'type', 'title', 'path'];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
