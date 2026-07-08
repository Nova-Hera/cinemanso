<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WheelDraw extends Model
{
    protected $fillable = ['movie_id', 'drawn_at'];

    protected $casts = ['drawn_at' => 'datetime'];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }
}
