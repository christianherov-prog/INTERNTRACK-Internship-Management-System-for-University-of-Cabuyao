<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorInviteToken extends Model
{
        protected $fillable = [
        'internship_id', 'student_id', 'token', 'expires_at', 'status',
        'supervisor_user_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email',
        'contact_number', 'position', 'company_id',
        'fo29_file_path', 'acceptance_form_paths',
        'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'reviewed_at' => 'datetime',
        'acceptance_form_paths' => 'array',
    ];

    protected $appends = ['acceptance_forms'];

    public function internship() { return $this->belongsTo(Internship::class); }
    public function student()    { return $this->belongsTo(User::class, 'student_id'); }
    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_user_id'); }
    public function company()    { return $this->belongsTo(Company::class); }
    public function reviewer()   { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function getAcceptanceFormsAttribute(): array
    {
        $stored = $this->acceptance_form_paths;
        if (is_array($stored) && $stored !== []) {
            return array_values(array_map(function ($item) {
                if (is_string($item)) {
                    return [
                        'path' => $item,
                        'name' => basename($item),
                        'mime' => null,
                    ];
                }

                return [
                    'path' => $item['path'] ?? null,
                    'name' => $item['name'] ?? basename((string) ($item['path'] ?? '')),
                    'mime' => $item['mime'] ?? null,
                ];
            }, $stored));
        }

        if ($this->fo29_file_path) {
            return [[
                'path' => $this->fo29_file_path,
                'name' => basename($this->fo29_file_path),
                'mime' => null,
            ]];
        }

        return [];
    }
}
