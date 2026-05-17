<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'status',
        'reference',
        'counterparty_id',
        'counterparty_name',
        'description',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
