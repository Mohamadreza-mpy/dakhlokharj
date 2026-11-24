<?php

namespace App\Models;

use App\Enums\ConsumptionEntryType;
use Database\Factories\ConsumptionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['purchase_id', 'room_id', 'consumer_member_id', 'quantity_milliunits', 'allocated_amount_toman', 'entry_type', 'reverses_entry_id', 'consumed_at', 'note', 'created_by_user_id', 'request_id', 'payload_hash', 'result_remaining_quantity_milliunits'])]
class ConsumptionEntry extends Model
{
    /** @use HasFactory<ConsumptionEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'entry_type' => ConsumptionEntryType::class,
            'quantity_milliunits' => 'integer',
            'allocated_amount_toman' => 'integer',
            'consumed_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'consumer_member_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }
}
