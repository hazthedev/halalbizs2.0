<?php

namespace App\Livewire\Admin\Coins;

use App\Models\CoinWallet;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin Loyalty Coins economy (M2.1 oversight): circulation at a glance, the
 * biggest wallets, and a grant/clawback control. All mutations route through
 * CoinService under the wallet lock.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $search = '';

    public ?int $adjustUserId = null;

    public string $adjustCoins = '';

    public string $adjustReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

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
        $wallets = CoinWallet::query()
            ->with('user')
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->orderByDesc('balance')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.coins.index', [
            'wallets' => $wallets,
            'circulation' => $coins->circulationCoins(),
            'walletCount' => CoinWallet::count(),
        ])->title(__('Loyalty Coins'));
    }
}
