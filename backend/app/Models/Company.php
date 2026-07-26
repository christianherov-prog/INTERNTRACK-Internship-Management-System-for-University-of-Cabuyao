<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name', 'address', 'industry', 'contact_person', 'contact_email',
        'contact_number', 'moa_status', 'moa_start_date', 'moa_expiry_date',
        'moa_file_path', 'slots_available', 'notes', 'is_active',
    ];

    protected $casts = [
        'moa_start_date' => 'date',
        'moa_expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    public function getMoaExpiresInDaysAttribute(): ?int
    {
        if (!$this->moa_expiry_date) {
            return null;
        }

        return now()->diffInDays($this->moa_expiry_date, false);
    }

    /** Companies that may receive a new placement. */
    public function scopeEligibleForPlacement(Builder $query): Builder
    {
        return $query
            ->where('moa_status', 'active')
            ->where('is_active', true)
            ->where('slots_available', '>', 0)
            ->where(function (Builder $q) {
                $q->whereNull('moa_expiry_date')
                    ->orWhereDate('moa_expiry_date', '>=', now()->toDateString());
            });
    }

    public function isEligibleForPlacement(): bool
    {
        if ($this->moa_status !== 'active' || !$this->is_active || (int) $this->slots_available <= 0) {
            return false;
        }

        if ($this->moa_expiry_date && $this->moa_expiry_date->lt(now()->startOfDay())) {
            return false;
        }

        return true;
    }

    public function ineligibilityReason(): string
    {
        if (!$this->is_active) {
            return 'This company is inactive and cannot receive placements.';
        }
        if ($this->moa_status !== 'active') {
            return 'Company MOA is not active (status: '.$this->moa_status.').';
        }
        if ($this->moa_expiry_date && $this->moa_expiry_date->lt(now()->startOfDay())) {
            return 'Company MOA has expired.';
        }
        if ((int) $this->slots_available <= 0) {
            return 'This company has no available internship slots.';
        }

        return 'Company is not eligible for placement.';
    }

    public function consumeSlot(): void
    {
        $this->slots_available = max(0, (int) $this->slots_available - 1);
        $this->save();
    }

    public function releaseSlot(): void
    {
        $this->slots_available = (int) $this->slots_available + 1;
        $this->save();
    }
}
