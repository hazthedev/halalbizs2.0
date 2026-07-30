<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated entry in a certificate's audit trail: issued, scope extended,
 * surveillance audit passed, renewed. Recorded rather than edited — the trail is
 * the evidence, so rewriting history would defeat it.
 */
class HalalCertificateEvent extends Model
{
    use HasFactory;

    protected $fillable = ['halal_certificate_id', 'occurred_on', 'summary'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(HalalCertificate::class, 'halal_certificate_id');
    }
}
