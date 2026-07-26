<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalEntry extends Model {
    use SoftDeletes;
    protected $fillable = ['internship_id','entry_number','date','activities_summary','learnings','challenges','status','supervisor_feedback','supervisor_reviewed_by','supervisor_reviewed_at','faculty_feedback','faculty_reviewed_by','faculty_reviewed_at', 'week_number', 'file_path', 'notes'];
    protected $casts = ['date'=>'date','supervisor_reviewed_at'=>'datetime','faculty_reviewed_at'=>'datetime'];
    public function internship() { return $this->belongsTo(Internship::class); }
    public function supervisorReviewer() { return $this->belongsTo(User::class,'supervisor_reviewed_by'); }
    public function facultyReviewer() { return $this->belongsTo(User::class,'faculty_reviewed_by'); }
}
