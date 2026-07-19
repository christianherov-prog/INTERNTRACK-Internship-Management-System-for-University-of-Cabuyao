<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Evaluation extends Model {
    use SoftDeletes;
    protected $fillable = ['internship_id','evaluator_type','evaluated_by','evaluation_period','technical_skills','communication_skills','teamwork','initiative','work_ethics','attendance_punctuality','adaptability','problem_solving','total_score','average_score','rating','strengths','areas_for_improvement','general_comments','submitted_at'];
    protected $casts = ['submitted_at'=>'datetime'];
    public function internship() { return $this->belongsTo(Internship::class); }
    public function evaluator() { return $this->belongsTo(User::class,'evaluated_by'); }
    public function computeScores():void { $fields=['technical_skills','communication_skills','teamwork','initiative','work_ethics','attendance_punctuality','adaptability','problem_solving']; $scores=array_map(fn($f)=>(float)$this->$f,$fields); $total=array_sum($scores); $avg=$total/count($fields); $this->total_score=$total; $this->average_score=round($avg,2); $this->rating=match(true){$avg>=4.5=>'Excellent',$avg>=3.5=>'Very Good',$avg>=2.5=>'Good',$avg>=1.5=>'Fair',default=>'Needs Improvement'}; }
}
