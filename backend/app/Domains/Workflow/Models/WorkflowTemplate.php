<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    use HasFactory;

    protected $table = 'workflow_templates';

    protected $fillable = [
        'name',
        'category',
        'description',
        'trigger_type',
        'definition',
        'is_system',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_system' => 'boolean',
    ];
}
