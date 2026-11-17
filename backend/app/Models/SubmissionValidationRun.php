<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionValidationRun extends Model
{
    protected $fillable = ['document_file_id', 'run_number', 'status', 'code_source', 'extracted_code', 'findings', 'checked_by_user_id', 'checked_at'];

    protected function casts(): array
    {
        return ['findings' => 'array', 'checked_at' => 'datetime'];
    }
}
