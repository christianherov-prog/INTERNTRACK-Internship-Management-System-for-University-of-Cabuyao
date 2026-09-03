<?php
namespace App\Models;

use App\Support\SignatureCapture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model {
    use SoftDeletes;

    protected $fillable = [
        'internship_id',
        'evaluator_type',
        'evaluated_by',
        'evaluation_period',
        'form_type',
        'responses',
        'total_score',
        'average_score',
        'rating',
        'general_comments',
        'submitted_at',
        'signer_name',
        'signature_path',
        'signed_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'signed_at' => 'datetime',
        'responses' => 'json'
    ];

    protected $appends = ['signature_url'];

    public function internship() {
        return $this->belongsTo(Internship::class);
    }

    public function evaluator() {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function getSignatureUrlAttribute(): ?string {
        return SignatureCapture::url($this->signature_path);
    }

    public function computeScores(): void {
        if (!$this->responses || !is_array($this->responses)) {
            return;
        }

        if ($this->form_type === 'FO-24') {
            // FO-24: 10 weighted criteria, 65-100 scale
            // Keys: c1...c10
            $weights = [
                'c1' => 0.25,
                'c2' => 0.125,
                'c3' => 0.125,
                'c4' => 0.10,
                'c5' => 0.10,
                'c6' => 0.10,
                'c7' => 0.05,
                'c8' => 0.05,
                'c9' => 0.05,
                'c10' => 0.05,
            ];

            $total = 0;
            foreach ($weights as $key => $weight) {
                if (isset($this->responses[$key])) {
                    $total += (float)$this->responses[$key] * $weight;
                }
            }

            $this->total_score = $total;
            $this->average_score = round($total, 2);

            // 96-100 = Excellent, 90-95 = Very Good, 85-89 = Good, 80-84 = Fair, 75-79 = Passed, <75 = Failed
            $avg = $this->average_score;
            $this->rating = match(true) {
                $avg >= 96 => 'Excellent',
                $avg >= 90 => 'Very Good',
                $avg >= 85 => 'Good',
                $avg >= 80 => 'Fair',
                $avg >= 75 => 'Passed',
                default => 'Failed'
            };
        } else {
            // FO-03, FO-22, FO-23: 1-5 scale items, simply calculate the unweighted average
            // Assuming responses contain numeric ratings keyed by 'q1', 'q2', etc.
            // Filter out non-numeric answers (like text comments)
            $ratings = [];
            foreach ($this->responses as $key => $value) {
                if (is_numeric($value) && str_starts_with($key, 'q')) {
                    $ratings[] = (float)$value;
                }
            }

            if (count($ratings) > 0) {
                $avg = array_sum($ratings) / count($ratings);
                $this->total_score = array_sum($ratings);
                $this->average_score = round($avg, 2);

                $this->rating = match(true) {
                    $avg >= 4.5 => 'Outstanding',
                    $avg >= 3.5 => 'Very Satisfactory',
                    $avg >= 2.5 => 'Satisfactory',
                    $avg >= 1.5 => 'Unsatisfactory',
                    default => 'Poor'
                };
            }
        }
    }
}
