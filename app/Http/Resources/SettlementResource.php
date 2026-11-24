<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'room_id' => (int) $this->room_id,
            'from_member_id' => (int) $this->from_member_id,
            'from_member' => $this->whenLoaded('fromMember', fn () => [
                'id' => (int) $this->fromMember->id,
                'display_name' => $this->fromMember->display_name,
            ]),
            'to_member_id' => (int) $this->to_member_id,
            'to_member' => $this->whenLoaded('toMember', fn () => [
                'id' => (int) $this->toMember->id,
                'display_name' => $this->toMember->display_name,
            ]),
            'amount_toman' => (int) $this->amount_toman,
            'entry_type' => $this->entry_type->value,
            'reverses_settlement_id' => $this->reverses_settlement_id,
            'paid_at' => $this->paid_at->toIso8601String(),
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
