<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'description', 'is_active', 'approval_flow_profile'])]
class DocumentType extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
