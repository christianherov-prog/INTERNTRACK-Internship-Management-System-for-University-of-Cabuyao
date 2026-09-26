<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A student-authored rich-text section of the internship portfolio
 * (e.g. biographical sketch, acknowledgment, narrative insights).
 * Content is sanitized HTML (see App\Support\PortfolioHtml).
 */
class PortfolioSection extends Model
{
    /** Section keys a student may author. */
    public const KEYS = [
        'bio_sketch',
        'acknowledgment',
        'company_profile',
        'narrative',
        'rec_students',
        'rec_program',
        'rec_hte',
    ];

    protected $fillable = [
        'student_portfolio_id',
        'section_key',
        'content',
        'updated_by',
    ];

    public function portfolio()
    {
        return $this->belongsTo(StudentPortfolio::class, 'student_portfolio_id');
    }
}
