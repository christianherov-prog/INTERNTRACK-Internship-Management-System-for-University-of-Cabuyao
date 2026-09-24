<?php
namespace App\Models;
use App\Services\SupervisorFeedbackService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalEntry extends Model {
    use SoftDeletes;
    protected $fillable = ['internship_id','entry_number','date','end_date','activities_summary','learnings','challenges','status','supervisor_feedback','supervisor_reviewed_by','supervisor_reviewed_at','faculty_feedback','faculty_reviewed_by','faculty_reviewed_at', 'week_number', 'file_path', 'notes', 'score', 'submitted_at', 'deadline_at', 'submitted_late'];
    protected $casts = ['date'=>'date','end_date'=>'date','supervisor_reviewed_at'=>'datetime','faculty_reviewed_at'=>'datetime','submitted_at'=>'datetime','deadline_at'=>'datetime','submitted_late'=>'boolean'];
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

    public function isSupervisorNote(): bool
    {
        return $this->status === SupervisorFeedbackService::NOTE_STATUS
            || (int) $this->week_number === 0;
    }

    public function scopeAcademic($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', SupervisorFeedbackService::NOTE_STATUS);
        })->where(function ($q) {
            $q->whereNull('week_number')->orWhere('week_number', '>=', 1);
        });
    }

    public function scopePendingSupervisorReview($query)
    {
        // Industry supervisors no longer review journals. Kept for historical queries.
        return $query->whereRaw('1 = 0');
    }

    public function scopePendingFacultyReview($query)
    {
        return $query->academic()->where('status', 'submitted');
    }

    public function isAwaitingSupervisorValidation(): bool
    {
        return false;
    }

    public function facultyCanReview(): bool
    {
        if ($this->isSupervisorNote()) {
            return false;
        }

        return in_array($this->status, ['submitted', 'needs_revision', 'approved'], true);
    }
}
