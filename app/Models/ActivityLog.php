<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'category',
        'description',
        'subject_type',
        'subject_id',
        'method',
        'path',
        'status_code',
        'ip_address',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'status_code' => 'integer',
            'properties' => 'array',
        ];
    }
}
