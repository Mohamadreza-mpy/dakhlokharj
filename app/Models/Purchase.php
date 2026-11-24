<?php

namespace App\Models;

use App\Enums\PurchaseKind;
use App\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['room_id', 'payer_member_id', 'title', 'kind', 'amount_toman', 'unit_label', 'total_quantity_milliunits', 'share_mode', 'purchased_at', 'note', 'status', 'voided_at', 'void_note', 'voided_by_user_id', 'created_by_user_id', 'request_id', 'payload_hash', 'void_request_id', 'void_payload_hash'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => PurchaseKind::class,
            'status' => PurchaseStatus::class,
            'amount_toman' => 'integer',
            'total_quantity_milliunits' => 'integer',
            'purchased_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class, 'payer_member_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ConsumptionEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SharedAllocation::class);
    }
}
