<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementTemplateAttachment extends Model
{
    protected $fillable = ['requirement_template_id', 'file_path', 'file_name'];

    public function requirementTemplate()
    {
        return $this->belongsTo(OjtRequirementTemplate::class);
    }
}
