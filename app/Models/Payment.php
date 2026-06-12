<?php

namespace App\Models;

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'gateway', 'ref_no', 'amount_sen', 'currency', 'status',
        'ipay88_payment_id', 'ipay88_trans_id', 'ipay88_auth_code',
        'signature_valid', 'requery_result', 'request_payload', 'response_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentMethod::class,
            'status' => GatewayPaymentStatus::class,
            'amount_sen' => 'integer',
            'signature_valid' => 'boolean',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
