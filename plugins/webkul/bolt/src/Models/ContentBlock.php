<?php

namespace Webkul\Bolt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Security\Models\User;

class ContentBlock extends Model
{
    protected $table = 'bolt_content_blocks';

    protected $fillable = [
        'name',
        'type',
        'content',
        'sort',
        'is_active',
        'page_id',
        'creator_id',
    ];

    protected $casts = [
        'content'   => 'array',
        'is_active' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
