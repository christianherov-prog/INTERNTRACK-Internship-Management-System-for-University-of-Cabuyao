<?php
namespace App\Models;
use App\Support\NameParts;
use Illuminate\Database\Eloquent\Model;
class FacultyProfile extends Model {
    protected $fillable = [
        'user_id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'contact_number', 'sex', 'department', 'college', 'position',
        'employment_status', 'synced_at',
    ];
    protected $casts = ['synced_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute(): string
    {
        return NameParts::fromProfile($this) ?: trim("{$this->first_name} {$this->last_name}");
    }
}
