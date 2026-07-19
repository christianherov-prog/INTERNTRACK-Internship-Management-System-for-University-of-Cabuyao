<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Company extends Model {
    use SoftDeletes;
    protected $fillable = ['company_name','address','industry','contact_person','contact_email','contact_number','moa_status','moa_start_date','moa_expiry_date','moa_file_path','slots_available','notes','is_active'];
    protected $casts = ['moa_start_date'=>'date','moa_expiry_date'=>'date','is_active'=>'boolean'];
    public function internships() { return $this->hasMany(Internship::class); }
    public function getMoaExpiresInDaysAttribute():?int { if(!$this->moa_expiry_date) return null; return now()->diffInDays($this->moa_expiry_date,false); }
}
