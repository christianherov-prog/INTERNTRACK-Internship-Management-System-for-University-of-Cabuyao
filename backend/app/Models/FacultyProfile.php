<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FacultyProfile extends Model {
    protected $fillable = ['user_id','employee_number','first_name','middle_name','last_name','email','contact_number','department','college','position','employment_status','synced_at'];
    protected $casts = ['synced_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute():string { return trim("$this->first_name $this->last_name"); }
}
