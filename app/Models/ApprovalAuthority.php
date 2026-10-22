<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAuthority extends Model
{
    protected $fillable = [
        'workflow_key',
        'label_ar',
        'permission_slug',
        'assigned_role_id',
    ];

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }
}
