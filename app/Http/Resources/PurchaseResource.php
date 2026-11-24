<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remaining = $this->total_quantity_milliunits === null
            ? null
            : (int) $this->total_quantity_milliunits - (int) ($this->consumed_quantity_milliunits ?? 0);

        return [
            'id' => (int) $this->id,
            'room_id' => (int) $this->room_id,
            'payer_member_id' => (int) $this->payer_member_id,
            'payer' => $this->whenLoaded('payer', fn () => [
                'id' => (int) $this->payer->id,
                'display_name' => $this->payer->display_name,
            ]),
            'title' => $this->title,
            'kind' => $this->kind->value,
            'amount_toman' => (int) $this->amount_toman,
            'unit_label' => $this->unit_label,
            'total_quantity_milliunits' => $this->total_quantity_milliunits === null ? null : (int) $this->total_quantity_milliunits,
            'remaining_quantity_milliunits' => $remaining,
            'share_mode' => $this->share_mode,
            'purchased_at' => $this->purchased_at->toIso8601String(),
            'note' => $this->note,
            'status' => $this->status->value,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_note' => $this->void_note,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation): array => [
                'id' => (int) $allocation->id,
                'room_member_id' => (int) $allocation->room_member_id,
                'member_name' => $allocation->roomMember->display_name,
                'amount_toman' => (int) $allocation->amount_toman,
            ])),
            'consumptions' => $this->whenLoaded('consumptions', fn () => $this->consumptions->map(fn ($entry): array => [
                'id' => (int) $entry->id,
                'consumer_member_id' => (int) $entry->consumer_member_id,
                'consumer_name' => $entry->consumer->display_name,
                'quantity_milliunits' => (int) $entry->quantity_milliunits,
                'allocated_amount_toman' => (int) $entry->allocated_amount_toman,
                'entry_type' => $entry->entry_type->value,
                'reverses_entry_id' => $entry->reverses_entry_id,
                'consumed_at' => $entry->consumed_at->toIso8601String(),
                'note' => $entry->note,
                'created_at' => $entry->created_at->toIso8601String(),
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
