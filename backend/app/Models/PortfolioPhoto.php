<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioPhoto extends Model
{
    protected $fillable = [
        'portfolio_id',
        'file_path',
        'label',
        'description',
        'type',
        'week_number',
        'date_taken',
        'sort_order',
    ];

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
