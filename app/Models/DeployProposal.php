<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeployProposal extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SUPERSEDED = 'superseded';

    public const STATUS_LABELS = [
        self::PENDING => 'Menunggu keputusan',
        self::APPROVED => 'Dieksekusi',
        self::REJECTED => 'Ditolak / revisi',
        self::SUPERSEDED => 'Digantikan usulan baru',
    ];

    protected $fillable = ['sha', 'token', 'title', 'report', 'pr_url', 'pr_number', 'status', 'owner_note', 'decided_at', 'error'];

    protected $hidden = ['otp_hash'];

    protected $casts = ['otp_expires_at' => 'datetime', 'decided_at' => 'datetime', 'pr_number' => 'integer', 'otp_attempts' => 'integer'];

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
