<?php

namespace App\Observers;

use App\Enums\PayoutStatus;
use App\Enums\ReturnStatus;
use App\Enums\StoreStatus;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * One observer, four hot spots — notifies EVERY admin-role user (database
 * only) without touching the forbidden service/Livewire files. Registered
 * in AppServiceProvider against Store, Payout, ReturnRequest and Payment.
 *
 * - Store created pending      → new seller application
 * - Payout created requested   → payout awaiting review
 * - ReturnRequest → escalated/disputed → dispute needs an admin decision
 * - Payment signature_valid flipped false → iPay88 signature mismatch
 */
class AdminAlertObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof Store && $model->status === StoreStatus::Pending) {
            $this->alertAdmins(
                __('New seller application — :store', ['store' => $model->name]),
                route('admin.sellers.applications'),
            );
        }

        if ($model instanceof Payout && $model->status === PayoutStatus::Requested) {
            $this->alertAdmins(
                __('Payout requested — :no · :amount', [
                    'no' => $model->payout_no,
                    'amount' => Money::format($model->amount_sen),
                ]),
                route('admin.finance.payouts'),
            );
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof ReturnRequest
            && $model->wasChanged('status')
            && in_array($model->status, [ReturnStatus::Escalated, ReturnStatus::Disputed], true)) {
            $this->alertAdmins(
                __('Return :status — :no', [
                    'status' => mb_strtolower($model->status->label()),
                    'no' => $model->subOrder?->sub_order_no ?? "#{$model->id}",
                ]),
                route('admin.orders.returns'),
            );
        }

        if ($model instanceof Payment
            && $model->wasChanged('signature_valid')
            && $model->signature_valid === false) {
            $this->alertAdmins(
                __('iPay88 signature mismatch — :ref', ['ref' => $model->ref_no ?? "payment #{$model->id}"]),
                route('admin.payments.index'),
            );
        }
    }

    private function alertAdmins(string $message, string $url): void
    {
        // whereHas (not User::role()) — the role scope throws when the
        // 'admin' role hasn't been seeded yet (fresh installs, tests).
        $admins = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AdminAlertNotification($message, $url));
        }
    }
}
