<?php

namespace App\Http\Resources;

use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductQuestion */
class ProductQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'asker_name' => $this->askerName(),
            'created_at' => $this->created_at?->toIso8601String(),
            'answer' => $this->isAnswered() ? $this->answer : null,
            'answered_at' => $this->answered_at?->toIso8601String(),
        ];
    }
}
