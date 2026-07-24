<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A lead (name/phone/need) shared by a customer in the CS assistant chat —
 * either mentioned in conversation or via the pre-WhatsApp contact form.
 * One row per chat session, upserted as more info arrives.
 */
class AssistantLead extends Model
{
    protected $fillable = ['session_id', 'name', 'phone', 'need'];
}
