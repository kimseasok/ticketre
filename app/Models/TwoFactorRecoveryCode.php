<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorRecoveryCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'two_factor_credential_id',
        'code_hash',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(TwoFactorCredential::class, 'two_factor_credential_id');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
