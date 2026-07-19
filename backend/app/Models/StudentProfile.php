<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentProfile extends Model {
    protected $fillable = ['user_id','student_number','first_name','middle_name','last_name','email','contact_number','birthday','sex','program','college','department','course_name','year_level','section','academic_year','semester','enrollment_status','synced_at'];
    protected $casts = ['synced_at'=>'datetime','birthday'=>'date'];
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute():string { return trim("$this->first_name $this->last_name"); }
}
