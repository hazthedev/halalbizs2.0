<?php

namespace App\Models;

use App\Support\MalaysianStates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'line1', 'line2',
        'postcode', 'city', 'state', 'country', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->whereKeyNot($address->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Validation for the address form, shared by the web account page and the
     * mobile API so both accept exactly the same shape.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['required', 'digits:5'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(MalaysianStates::ALL)],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot shape stored on orders.shipping_address.
     */
    public function toSnapshot(): array
    {
        return $this->only([
            'recipient_name', 'phone', 'line1', 'line2', 'postcode', 'city', 'state', 'country',
        ]);
    }
}
