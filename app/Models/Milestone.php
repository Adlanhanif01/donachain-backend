<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $hidden = ['proof_path'];

    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'amount_percentage',
        'proof_path',
        'status',
        'admin_note',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
