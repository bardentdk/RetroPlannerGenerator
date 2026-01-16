<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSlot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_present' => 'boolean',
        'date' => 'date',
    ];
}