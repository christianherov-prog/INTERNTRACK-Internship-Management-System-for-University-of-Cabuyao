<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Announcement extends Model {
    use SoftDeletes;
    protected $fillable = ['created_by','title','content','target_role','is_pinned','expires_at'];
    protected $casts = ['is_pinned'=>'boolean','expires_at'=>'datetime'];
    public function author() { return $this->belongsTo(User::class,'created_by'); }
}
