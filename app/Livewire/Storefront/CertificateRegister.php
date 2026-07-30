<?php

namespace App\Livewire\Storefront;

use App\Models\HalalCertificate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The public certificate register.
 *
 * Anyone with a number printed on a listing, an invoice or a carton can resolve
 * it here and see the record behind it: who issued it, who holds it, what scope
 * it covers, when it lapses, and what has happened to it. That lookup is what
 * makes the marketplace's headline claim checkable rather than asserted.
 *
 * Deliberately public and unauthenticated — a verification tool that requires a
 * login verifies nothing for the person who needs it.
 */
#[Layout('layouts.storefront')]
class CertificateRegister extends Component
{
    /** Bound to the URL so a resolved record can be linked and shared. */
    #[Url(as: 'no', except: '')]
    public string $number = '';

    public function verify(): void
    {
        $this->number = trim($this->number);
    }

    public function render()
    {
        $query = strtoupper(preg_replace('/\s+/', '', $this->number));

        $certificate = $query === '' ? null : HalalCertificate::query()
            ->with(['events', 'store'])
            ->withCount('products')
            ->whereRaw('UPPER(number) = ?', [$query])
            ->first();

        return view('livewire.storefront.certificate-register', [
            'certificate' => $certificate,
            'searched' => $query !== '',
            'bodies' => HalalCertificate::BODIES,
        ])->title(__('Certificate register'));
    }
}
