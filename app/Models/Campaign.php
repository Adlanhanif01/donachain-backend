<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $appends = ['thumbnail_url'];

    protected $fillable = [
        'creator_id',
        'title',
        'category',
        'description',
        'target_amount',
        'thumbnail_path',
        'status',
        'admin_note',
    ];

    protected $hidden = ['thumbnail_path'];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:18',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? route('campaigns.thumbnail', $this)
            : null;
    }
}
