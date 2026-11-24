<?php

namespace App\Models;

use Database\Factories\SharedAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_id', 'room_member_id', 'amount_toman'])]
class SharedAllocation extends Model
{
    /** @use HasFactory<SharedAllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_toman' => 'integer'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function roomMember(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class);
    }
}
