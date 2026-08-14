<?php

namespace App\Livewire\Admin\Catalog;

use App\Enums\CertificateStatus;
use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Notifications\HalalCertificateDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The halal certificate review queue (audit H-6).
 *
 * This is the half that makes the seller's submission mean anything: until an
 * admin approves it, halalVerdict() reads 'pending' and no badge renders. The
 * shape follows Admin\Sellers\Applications — inline review panel, approve or
 * reject with a reason, notify, activity-log.
 */
#[Layout('layouts.admin')]
class Certificates extends Component
{
    use WithPagination;

    public const PER_PAGE = 15;

    /** Certificate id whose review panel is open. */
    public ?int $reviewing = null;

    public string $rejectionReason = '';

    /** pending | approved | rejected | all */
    public string $filter = 'pending';

    public function updatedFilter(): void
    {
        $this->reviewing = null;
        $this->resetPage();
    }

    public function review(int $id): void
    {
        $this->reviewing = $this->reviewing === $id ? null : $id;
        $this->rejectionReason = '';
        $this->resetErrorBag();
    }

    public function approve(int $id): void
    {
        // Re-queried through the scoped builder and findOrFail rather than
        // trusting the posted id, the way Applications::approve does.
        $certificate = $this->certificates()->with('store.user')->findOrFail($id);

        DB::transaction(function () use ($certificate) {
            $certificate->forceFill([
                'status' => CertificateStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
                'review_note' => null,
            ])->save();

            // The events table is rendered on the PUBLIC register, so this line
            // is buyer-facing: state the fact, name no reviewer.
            HalalCertificateEvent::create([
                'halal_certificate_id' => $certificate->id,
                'occurred_on' => now()->toDateString(),
                'summary' => __('Verified by :app', ['app' => config('app.name')]),
            ]);
        });

        activity()
            ->causedBy(auth()->user())
            ->performedOn($certificate)
            ->withProperties(['number' => $certificate->number])
            ->log('halal_certificate.approved');

        $certificate->store?->user?->notify(new HalalCertificateDecision($certificate, 'approved'));

        $this->reviewing = null;
        $this->dispatch('toast', message: __(':number approved — the badge is live on its products.', ['number' => $certificate->number]));
    }

    public function reject(int $id): void
    {
        $this->validate(
            ['rejectionReason' => ['required', 'string', 'min:5', 'max:1000']],
            ['rejectionReason.required' => __('Tell the seller what was wrong — they see this word for word.')],
        );

        $certificate = $this->certificates()->with('store.user')->findOrFail($id);

        DB::transaction(function () use ($certificate) {
            $certificate->forceFill([
                'status' => CertificateStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
                'review_note' => $this->rejectionReason,
            ])->save();

            // No event row on a rejection: the register is public, and a
            // rejected certificate should leave no trace on a page buyers read.
        });

        activity()
            ->causedBy(auth()->user())
            ->performedOn($certificate)
            ->withProperties(['number' => $certificate->number, 'reason' => $this->rejectionReason])
            ->log('halal_certificate.rejected');

        $certificate->store?->user?->notify(new HalalCertificateDecision($certificate, 'rejected', $this->rejectionReason));

        $this->reviewing = null;
        $this->rejectionReason = '';
        $this->dispatch('toast', message: __(':number rejected — the seller has your note.', ['number' => $certificate->number]));
    }

    private function certificates(): Builder
    {
        return HalalCertificate::query()
            ->when($this->filter !== 'all', fn (Builder $q) => $q->where('status', $this->filter));
    }

    public function render()
    {
        return view('livewire.admin.catalog.certificates', [
            'certificates' => $this->certificates()
                ->with(['store', 'products:id,halal_certificate_id', 'media'])
                // Oldest submission first: a review queue is a queue.
                ->orderByRaw('submitted_at IS NULL, submitted_at ASC')
                ->paginate(self::PER_PAGE),
            'pendingCount' => HalalCertificate::query()->awaitingReview()->count(),
        ])->title(__('Halal certificates'));
    }
}
