<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'email', 'successful', 'ip_address', 'user_agent', 'created_at'];

    protected $casts = ['successful' => 'boolean', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
