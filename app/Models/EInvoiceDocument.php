<?php

namespace App\Models;

use App\Enums\EInvoiceStatus;
use App\Enums\EInvoiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EInvoiceDocument extends Model
{
    use HasFactory;

    protected $table = 'einvoice_documents';

    protected $fillable = [
        'store_id', 'sub_order_id', 'order_id', 'provider', 'type', 'period',
        'status', 'total_sen', 'tax_sen', 'submission_uid', 'uin',
        'validation_url', 'error', 'submitted_at', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EInvoiceType::class,
            'status' => EInvoiceStatus::class,
            'total_sen' => 'integer',
            'tax_sen' => 'integer',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
