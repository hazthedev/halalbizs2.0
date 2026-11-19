<?php

namespace App\Livewire\Admin\Coins;

use App\Models\CoinWallet;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\WithTable;
use App\Support\JsonSearch;
use Livewire\Component;

/**
 * Admin Loyalty Coins economy (M2.1 oversight): circulation at a glance, the
 * biggest wallets, and a grant/clawback control. All mutations route through
 * CoinService under the wallet lock.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['balance' => 'balance', 'lifetime' => 'lifetime_earned'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['balance' => 'desc'];
    }

    public ?int $adjustUserId = null;

    public string $adjustCoins = '';

    public string $adjustReason = '';

    public function openAdjust(int $userId): void
    {
        $this->adjustUserId = $userId;
        $this->adjustCoins = '';
        $this->adjustReason = '';
        $this->resetValidation();
    }

    public function adjust(CoinService $coins): void
    {
        $validated = $this->validate([
            'adjustCoins' => ['required', 'integer', 'not_in:0'],
            'adjustReason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $user = User::find($this->adjustUserId);

        if ($user === null) {
            return;
        }

        $applied = $coins->adminAdjust($user, (int) $validated['adjustCoins'], trim($validated['adjustReason']));

        $this->reset('adjustUserId', 'adjustCoins', 'adjustReason');
        $this->dispatch('toast', message: __(':n coins applied to :name.', ['n' => $applied, 'name' => $user->name]));
    }

    public function render(CoinService $coins): View
    {
        $term = JsonSearch::pattern($this->search);

        $wallets = $this->sortTable(CoinWallet::query()
            ->with('user')
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->whereRaw(JsonSearch::clause('name'), [$term])
                ->orWhereRaw(JsonSearch::clause('email'), [$term]))))
            ->paginate($this->tablePageSize());

        return view('livewire.admin.coins.index', [
            'wallets' => $wallets,
            'circulation' => $coins->circulationCoins(),
            'walletCount' => CoinWallet::count(),
        ])->title(__('Loyalty Coins'));
    }
}
