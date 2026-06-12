<?php

namespace App\Livewire\Admin\Sellers\Concerns;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared per-document verify/reject controls (docs/08 §B) — used by the
 * applications queue and the store detail re-verification list.
 */
trait ReviewsDocuments
{
    /** @var array<int, string> Reviewer notes keyed by document id. */
    public array $docNotes = [];

    public function verifyDocument(int $documentId): void
    {
        $this->reviewDocument($documentId, DocumentStatus::Verified);

        $this->dispatch('toast', message: __('Document verified'));
    }

    public function rejectDocument(int $documentId): void
    {
        $this->reviewDocument($documentId, DocumentStatus::Rejected);

        $this->dispatch('toast', message: __('Document rejected'));
    }

    private function reviewDocument(int $documentId, DocumentStatus $status): void
    {
        $document = $this->reviewableDocuments()->findOrFail($documentId);

        $notes = trim((string) ($this->docNotes[$documentId] ?? ''));

        $document->update([
            'status' => $status,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }

    /** Scope: the documents this screen is allowed to review. */
    abstract protected function reviewableDocuments(): Builder|HasMany;
}
