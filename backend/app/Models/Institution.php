<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $fillable = [
        'name', 'short_name', 'contact_details', 'facility_type', 'timezone', 'status', 'logo_path',
    ];

    public static function localDate(): string
    {
        $timezone = self::query()->whereKey(1)->value('timezone') ?: config('app.timezone');

        return now($timezone)->toDateString();
    }
}
