<?php

namespace App\Livewire\Seller\Questions;

use App\Livewire\Concerns\CurrentStore;
use App\Models\ProductQuestion;
use App\Notifications\ProductQuestionAnswered;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Store question inbox — buyer questions on the store's products, unanswered
 * first. ONE answer per question (editable). The seller can also hide a
 * question (spam/abuse moderation). Everything is store-scoped, never leaks.
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore, WithPagination;

    public ?int $answeringId = null;

    public string $answerText = '';

    public function startAnswer(int $questionId): void
    {
        $question = $this->ownQuestion($questionId);

        $this->answeringId = $question->id;
        $this->answerText = (string) $question->answer;
        $this->resetErrorBag();
    }

    public function cancelAnswer(): void
    {
        $this->reset('answeringId', 'answerText');
        $this->resetErrorBag();
    }

    public function saveAnswer(): void
    {
        if ($this->answeringId === null) {
            return;
        }

        $question = $this->ownQuestion($this->answeringId);

        $this->validate([
            'answerText' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'answerText.required' => __('Write your answer before posting it.'),
        ]);

        $firstAnswer = $question->answered_at === null;

        $question->update([
            'answer' => trim($this->answerText),
            'answered_by' => auth()->id(),
            'answered_at' => $question->answered_at ?? now(),
        ]);

        if ($firstAnswer) {
            $question->user?->notify(new ProductQuestionAnswered($question));
        }

        $this->cancelAnswer();
        $this->dispatch('toast', message: __('Answer posted — buyers see it on the product page.'));
    }

    public function hide(int $questionId): void
    {
        $this->ownQuestion($questionId)->update(['is_hidden' => true]);

        $this->dispatch('toast', message: __('Question hidden from the product page.'));
    }

    public function render()
    {
        return view('livewire.seller.questions.index', [
            'questions' => ProductQuestion::query()
                ->where('store_id', $this->currentStore()->id)
                ->with(['product', 'user', 'answerer'])
                ->orderByRaw('answered_at is null desc') // unanswered first
                ->latest('id')
                ->paginate(10),
        ])->title(__('Questions'));
    }

    /** Store-scoped lookup — other stores' questions 404 (never leak). */
    private function ownQuestion(int $questionId): ProductQuestion
    {
        $question = ProductQuestion::query()
            ->where('store_id', $this->currentStore()->id)
            ->find($questionId);

        abort_if($question === null, 404);

        return $question;
    }
}
