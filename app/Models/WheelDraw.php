<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WheelDraw extends Model
{
    protected $fillable = ['movie_id', 'drawn_at', 'target_angle', 'segments'];

    protected $casts = [
        'drawn_at'     => 'datetime',
        'target_angle' => 'float',
        'segments'     => 'array',
    ];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }
}
