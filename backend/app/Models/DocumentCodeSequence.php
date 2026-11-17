<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCodeSequence extends Model
{
    protected $fillable = ['code_prefix', 'next_sequence'];
}
