<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A lead (name/phone) shared by a customer in the CS assistant chat. One row
 * per chat session, upserted as more info arrives.
 */
class AssistantLead extends Model
{
    protected $fillable = ['session_id', 'name', 'phone'];
}
