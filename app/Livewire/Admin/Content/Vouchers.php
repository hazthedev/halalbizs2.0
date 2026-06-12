<?php

namespace App\Livewire\Admin\Content;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Support\RinggitInput;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * PLATFORM voucher CRUD (docs/08 §G) — scope platform, store_id null.
 * This screen only manages rows; checkout already validates platform
 * vouchers (voucher_lite). The full engine (shop vouchers, stacking) is M8.
 */
#[Layout('layouts.admin')]
class Vouchers extends Component
{
    public bool $showForm = false;

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

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $voucherId): void
    {
        $voucher = $this->platformVoucher($voucherId);

        $this->resetForm();
        $this->editingId = $voucher->id;
        $this->code = $voucher->code;
        $this->type = $voucher->type->value;
        $this->value = RinggitInput::fromSen($voucher->value_sen);
        $this->percent = $voucher->percent !== null ? (string) $voucher->percent : '';
        $this->maxDiscount = RinggitInput::fromSen($voucher->max_discount_sen);
        $this->minSpend = $voucher->min_spend_sen > 0 ? RinggitInput::fromSen($voucher->min_spend_sen) : '';
        $this->quota = $voucher->quota !== null ? (string) $voucher->quota : '';
        $this->perUserLimit = (string) $voucher->per_user_limit;
        $this->startsAt = $voucher->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $voucher->ends_at->format('Y-m-d\TH:i');
        $this->isActive = $voucher->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
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

        $starts = Carbon::parse($this->startsAt);
        $ends = Carbon::parse($this->endsAt);

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

        $this->dispatch('toast', message: $this->editingId !== null ? __('Voucher updated') : __('Voucher created'));
        $this->resetForm();
    }

    public function toggleActive(int $voucherId): void
    {
        $voucher = $this->platformVoucher($voucherId);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        $this->dispatch('toast', message: $voucher->is_active ? __('Voucher enabled') : __('Voucher disabled'));
    }

    public function delete(int $voucherId): void
    {
        $voucher = $this->platformVoucher($voucherId);

        if ($voucher->used_count > 0) {
            $this->dispatch('toast', message: __('This voucher has been used — disable it instead of deleting.'), type: 'error');

            return;
        }

        $voucher->delete();

        if ($this->editingId === $voucherId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Voucher deleted'));
    }

    public function render()
    {
        return view('livewire.admin.content.vouchers', [
            'vouchers' => Voucher::where('scope', VoucherScope::Platform)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get(),
            'types' => VoucherType::cases(),
        ])->title(__('Platform vouchers'));
    }

    /**
     * Per-type money fields. Fixed → value_sen; percent → percent + optional
     * max_discount_sen cap; free_shipping → none.
     *
     * @return array{0: int|null, 1: string|null, 2: int|null}
     */
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

    /** RM string → sen via RinggitInput; validation error when unparseable. */
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
        return Voucher::where('scope', VoucherScope::Platform)->findOrFail($voucherId);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm', 'editingId', 'code', 'type', 'value', 'percent', 'maxDiscount',
            'minSpend', 'quota', 'perUserLimit', 'startsAt', 'endsAt', 'isActive',
        ]);
        $this->resetErrorBag();
    }
}
