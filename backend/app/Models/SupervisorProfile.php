<?php
namespace App\Models;
use App\Support\NameParts;
use Illuminate\Database\Eloquent\Model;
class SupervisorProfile extends Model {
    protected $fillable = [
        'user_id', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'contact_number', 'sex', 'position',
    ];
    protected $appends = ['full_name'];
    public function user() { return $this->belongsTo(User::class); }
    public function getFullNameAttribute(): string
    {
        return NameParts::fromProfile($this) ?: trim("{$this->last_name}, {$this->first_name}");
    }
}
