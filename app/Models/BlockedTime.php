<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\BlockedTimeType;
use Carbon\CarbonInterface;
use Database\Factories\BlockedTimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_member_id', 'type', 'reason', 'starts_at', 'ends_at', 'created_by_user_id'])]
class BlockedTime extends Model
{
    /** @use HasFactory<BlockedTimeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BlockedTimeType::class,
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * Blocks that apply to a staff member: their own, plus whole-shop closures.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function affecting(Builder $query, StaffMember $staffMember): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('staff_member_id')
            ->orWhere('staff_member_id', $staffMember->id));
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc());
    }
}
