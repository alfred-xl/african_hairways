<?php

namespace App\Models;

use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'job_title', 'color', 'tags', 'is_chair_renter', 'is_active', 'sort_order'])]
class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_chair_renter' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<ServiceCategory, $this>
     */
    public function serviceCategories(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class);
    }

    /**
     * @return HasMany<StaffWorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    /**
     * @return HasMany<BlockedTime, $this>
     */
    public function blockedTimes(): HasMany
    {
        return $this->hasMany(BlockedTime::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function offersCategory(int $serviceCategoryId): bool
    {
        return $this->serviceCategories->contains('id', $serviceCategoryId);
    }
}
