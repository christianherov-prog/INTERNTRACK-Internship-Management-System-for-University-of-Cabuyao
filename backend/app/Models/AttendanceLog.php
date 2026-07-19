<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class AttendanceLog extends Model {
    use SoftDeletes;
    protected $fillable = ['internship_id','date','clock_in','clock_out','hours_rendered','overtime_hours','status','remarks','validated_by','validated_at','clock_in_location','clock_out_location'];
    protected $casts = ['date'=>'date','validated_at'=>'datetime'];
    public function internship() { return $this->belongsTo(Internship::class); }
    public function validator() { return $this->belongsTo(User::class,'validated_by'); }
}
