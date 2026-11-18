<?php

namespace App\Livewire\Admin\Content\Vouchers;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Support\DisplayTime;
use App\Support\RinggitInput;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $type = 'fixed';

    public string $value = '';

    public string $percent = '';

    public string $maxDiscount = '';

    public string $minSpend = '';

    public string $quota = '';

    public string $perUserLimit = '1';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = true;

    public function mount(?Voucher $voucher = null): void
    {
        if ($voucher === null || ! $voucher->exists) {
            return;
        }

        $voucher = $this->platformVoucher($voucher->id);

        $this->editingId = $voucher->id;
        $this->code = $voucher->code;
        $this->type = $voucher->type->value;
        $this->value = RinggitInput::fromSen($voucher->value_sen);
        $this->percent = $voucher->percent !== null ? (string) $voucher->percent : '';
        $this->maxDiscount = RinggitInput::fromSen($voucher->max_discount_sen);
        $this->minSpend = $voucher->min_spend_sen > 0 ? RinggitInput::fromSen($voucher->min_spend_sen) : '';
        $this->quota = $voucher->quota !== null ? (string) $voucher->quota : '';
        $this->perUserLimit = (string) $voucher->per_user_limit;
        $this->startsAt = $voucher->starts_at->display()->format('Y-m-d\TH:i');
        $this->endsAt = $voucher->ends_at->display()->format('Y-m-d\TH:i');
        $this->isActive = $voucher->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/'],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'quota' => ['nullable', 'integer', 'min:1'],
            'perUserLimit' => ['required', 'integer', 'min:1'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date'],
        ], [
            'code.regex' => __('Codes use letters, numbers, and dashes only.'),
        ]);

        $code = strtoupper(trim($this->code));
        $type = VoucherType::from($this->type);

        // Unique among PLATFORM vouchers — the DB unique (store_id, code)
        // doesn't catch store_id NULL duplicates, so enforce it here.
        $duplicate = Voucher::where('scope', VoucherScope::Platform)
            ->where('code', $code)
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => __('A platform voucher with this code already exists.'),
            ]);
        }

        [$valueSen, $percent, $maxDiscountSen] = $this->validateTypeFields($type);
        $minSpendSen = $this->validateRinggit('minSpend', $this->minSpend, allowEmpty: true) ?? 0;

        $starts = DisplayTime::parse($this->startsAt);
        $ends = DisplayTime::parse($this->endsAt);

        if ($ends->lte($starts)) {
            throw ValidationException::withMessages([
                'endsAt' => __('The voucher must end after it starts.'),
            ]);
        }

        $voucher = $this->editingId !== null ? $this->platformVoucher($this->editingId) : new Voucher;

        $voucher->fill([
            'scope' => VoucherScope::Platform,
            'store_id' => null,
            'code' => $code,
            'type' => $type,
            'value_sen' => $valueSen,
            'percent' => $percent,
            'max_discount_sen' => $maxDiscountSen,
            'min_spend_sen' => $minSpendSen,
            'quota' => $this->quota !== '' ? (int) $this->quota : null,
            'per_user_limit' => (int) $this->perUserLimit,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'is_active' => $this->isActive,
        ])->save();

        session()->flash('status', $this->editingId !== null ? __('Voucher updated') : __('Voucher created'));
        $this->redirectRoute('admin.content.vouchers', navigate: true);
    }

    private function validateTypeFields(VoucherType $type): array
    {
        if ($type === VoucherType::Fixed) {
            $valueSen = $this->validateRinggit('value', $this->value);

            if ($valueSen === null || $valueSen <= 0) {
                throw ValidationException::withMessages([
                    'value' => __('Enter an amount above RM 0 — e.g. 5.00.'),
                ]);
            }

            return [$valueSen, null, null];
        }

        if ($type === VoucherType::Percent) {
            if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', trim($this->percent))
                || (float) $this->percent <= 0 || (float) $this->percent > 100) {
                throw ValidationException::withMessages([
                    'percent' => __('Enter a percentage between 0 and 100 — e.g. 10.'),
                ]);
            }

            $maxDiscountSen = $this->validateRinggit('maxDiscount', $this->maxDiscount, allowEmpty: true);

            return [null, trim($this->percent), $maxDiscountSen];
        }

        return [null, null, null];
    }

    private function validateRinggit(string $field, string $input, bool $allowEmpty = false): ?int
    {
        if (trim($input) === '') {
            if ($allowEmpty) {
                return null;
            }

            throw ValidationException::withMessages([$field => __('Enter an amount in RM — e.g. 5.00.')]);
        }

        $sen = RinggitInput::toSen($input);

        if ($sen === null || $sen < 0) {
            throw ValidationException::withMessages([$field => __('Enter a valid RM amount — e.g. 5.00.')]);
        }

        return $sen;
    }

    private function platformVoucher(int $voucherId): Voucher
    {
        // Same scoping on the write path: an admin must not be able to edit or
        // delete a buyer's won prize through this screen.
        return Voucher::where('scope', VoucherScope::Platform)->whereNull('user_id')->findOrFail($voucherId);
    }

    public function render()
    {
        return view('livewire.admin.content.vouchers.form', ['types' => VoucherType::cases()])->title(__('Platform vouchers'));
    }
}
