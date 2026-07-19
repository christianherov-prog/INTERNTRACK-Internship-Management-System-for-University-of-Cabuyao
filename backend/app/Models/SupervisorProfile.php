<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupervisorProfile extends Model {
    protected $fillable = ['user_id','first_name','last_name','email','contact_number','position'];
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute():string { return trim("$this->first_name $this->last_name"); }
}
