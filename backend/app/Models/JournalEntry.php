<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalEntry extends Model {
    use SoftDeletes;
    protected $fillable = ['internship_id','entry_number','date','end_date','activities_summary','learnings','challenges','status','supervisor_feedback','supervisor_reviewed_by','supervisor_reviewed_at','faculty_feedback','faculty_reviewed_by','faculty_reviewed_at', 'week_number', 'file_path', 'notes', 'score'];
    protected $casts = ['date'=>'date','end_date'=>'date','supervisor_reviewed_at'=>'datetime','faculty_reviewed_at'=>'datetime'];
    public function internship() { return $this->belongsTo(Internship::class); }

    public function toArray(): array
    {
        $array = parent::toArray();
        if ($this->date) {
            $array['date'] = $this->date->toDateString();
        }
        if ($this->end_date) {
            $array['end_date'] = $this->end_date->toDateString();
        }

        return $array;
    }
    public function supervisorReviewer() { return $this->belongsTo(User::class,'supervisor_reviewed_by'); }
    public function facultyReviewer() { return $this->belongsTo(User::class,'faculty_reviewed_by'); }

    public function scopePendingSupervisorReview($query)
    {
        return $query->where('status', 'submitted')->whereNull('supervisor_reviewed_at');
    }

    public function scopePendingFacultyReview($query)
    {
        return $query->where('status', 'submitted')
            ->where(function ($q) {
                $q->whereNotNull('supervisor_reviewed_at')
                    ->orWhereHas('internship', fn ($internship) => $internship->whereNull('supervisor_id'));
            });
    }

    public function isAwaitingSupervisorValidation(): bool
    {
        return (bool) $this->internship?->supervisor_id
            && $this->supervisor_reviewed_at === null
            && $this->status === 'submitted';
    }

    public function facultyCanReview(): bool
    {
        if ($this->faculty_reviewed_at) {
            return true;
        }

        if (! $this->internship?->supervisor_id) {
            return true;
        }

        return $this->supervisor_reviewed_at !== null;
    }
}
