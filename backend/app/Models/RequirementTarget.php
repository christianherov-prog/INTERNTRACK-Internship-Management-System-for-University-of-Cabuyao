<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementTarget extends Model
{
    protected $table = 'ojt_requirement_targets';

    protected $fillable = [
        'requirement_template_id',
        'target_type',
        'target_id',
    ];

    public function requirementTemplate()
    {
        return $this->belongsTo(OjtRequirementTemplate::class, 'requirement_template_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'target_id')->where('target_type', 'student');
    }

    // Note: Sections are stored as plain strings (not a model), so there is no
    // Eloquent relationship for target_type='section'. Section matching is done
    // via string comparison in StudentController::documents().

    public function program()
    {
        return $this->belongsTo(Program::class, 'target_id')->where('target_type', 'program');
    }
}
