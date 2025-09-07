<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeclinedExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->created_at->format('Y-m-d H:i:s'),
            'requester_name' => $this->requester->full_name ?? 'Unknown',
            'description' => $this->description ?? '',
            'amount' => $this->amount,
        ];
    }
}
