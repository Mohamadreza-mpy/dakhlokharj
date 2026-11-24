<?php

namespace App\Models;

use App\Enums\SettlementEntryType;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['room_id', 'from_member_id', 'to_member_id', 'amount_toman', 'entry_type', 'reverses_settlement_id', 'paid_at', 'note', 'created_by_user_id', 'request_id', 'payload_hash'])]
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'entry_type' => SettlementEntryType::class,
            'amount_toman' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function fromMember(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'from_member_id');
    }

    public function toMember(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'to_member_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_settlement_id');
    }
}
