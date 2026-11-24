<?php

namespace App\Models;

use Database\Factories\RoomMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['room_id', 'user_id', 'display_name', 'role', 'is_active'])]
class RoomMember extends Model
{
    /** @use HasFactory<RoomMemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'payer_member_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ConsumptionEntry::class, 'consumer_member_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SharedAllocation::class);
    }
}
