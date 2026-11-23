<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Api\ApiResponse;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Notifications\BankDetailsChanged;
use App\Support\MalaysianBanks;
use App\Support\MalaysianStates;
use App\Support\UploadName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shop settings (docs/07 §A5) for the app: the same profile, shipping and
 * bank rules as Seller\Settings, with fees already in integer sen. The shop
 * name is not editable here either.
 */
class StoreController extends SellerController
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok(new StoreResource($this->currentStore($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['sometimes', 'required', 'string', 'max:2000'],
            'state' => ['sometimes', 'required', Rule::in(MalaysianStates::ALL)],
            'holiday_mode' => ['sometimes', 'boolean'],
            'shipping_mode' => ['sometimes', 'required', Rule::in(['flat', 'matrix'])],
            'shipping_flat_fee_sen' => ['required_if:shipping_mode,flat', 'integer', 'min:0'],
            'shipping_matrix' => ['required_if:shipping_mode,matrix', 'array'],
            'shipping_matrix.*' => ['integer', 'min:0'],
            'free_shipping_over_sen' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bank' => ['sometimes', 'required', 'array'],
            'bank.bank_name' => ['required_with:bank', Rule::in(MalaysianBanks::ALL)],
            'bank.account_name' => ['required_with:bank', 'string', 'max:120'],
            'bank.account_number' => ['required_with:bank', 'regex:/^[0-9]{8,17}$/'],
            // HB-LAW-14: optional seller details, same rules as Seller\Settings.
            ...array_map(fn (array $rule) => ['sometimes', ...$rule], Store::sellerDetailRules()),
        ], [
            'bank.account_number.regex' => __('Enter the account number as 8–17 digits, no spaces or dashes.'),
            'contact_phone.regex' => __('Use digits, spaces and dashes only, with an optional + at the start.'),
        ]);

        if (isset($data['shipping_matrix'])) {
            $missing = array_diff(MalaysianStates::ALL, array_keys($data['shipping_matrix']));

            if ($missing !== []) {
                return ApiResponse::error('validation_failed', __('Enter a fee for :state.', ['state' => reset($missing)]), 422, [
                    'shipping_matrix' => [__('Enter a fee for :state.', ['state' => reset($missing)])],
                ]);
            }
        }

        $store = $this->currentStore($request);

        // registration_no is also the WHIP certificate key: settable only while
        // blank, the same rule as the web settings and certificate forms.
        if (array_key_exists('registration_no', $data) && filled($store->registration_no)) {
            if ($data['registration_no'] !== $store->registration_no) {
                throw ValidationException::withMessages(['registration_no' => __('Contact support to change your registration number.')]);
            }
            unset($data['registration_no']);
        }

        $bankChanged = false;

        if (isset($data['bank'])) {
            // PAY-20: same rule as Seller\Settings — a real change needs the
            // current password; resending the saved details does not.
            if ($bankChanged = $store->bankDetailsDiffer($data['bank'])) {
                $request->validate(
                    ['current_password' => ['required', 'current_password']],
                    ['current_password.current_password' => __('That doesn\'t match your current password — try again.')],
                );
            }

            $data['bank_details'] = $data['bank'];
            unset($data['bank']);
        }

        $store->update($data);

        if ($bankChanged) {
            $store->user->notify(new BankDetailsChanged($store));
        }

        return ApiResponse::ok(new StoreResource($store->fresh()));
    }

    public function logo(Request $request): JsonResponse
    {
        return $this->upload($request, 'logo', 2048);
    }

    public function banner(Request $request): JsonResponse
    {
        return $this->upload($request, 'banner', 4096);
    }

    /** singleFile collections — a new upload replaces the previous one. */
    private function upload(Request $request, string $collection, int $maxKb): JsonResponse
    {
        $request->validate([$collection => ['required', 'image', "max:$maxKb"]]);

        $store = $this->currentStore($request);
        $file = $request->file($collection);

        $store->addMedia($file->getRealPath())
            ->usingFileName(UploadName::safe($file, $collection.'-'))
            ->toMediaCollection($collection);

        return ApiResponse::ok(new StoreResource($store->fresh()));
    }
}
