<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SosRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'latitude',
        'longitude',
        'coordinates',
        'status',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: responders if you want later expansion
    public function responders()
    {
        return $this->belongsToMany(
            Responder::class,
            'sos_responder'
        )->withTimestamps();
    }
}
