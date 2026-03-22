<?php

namespace Webkul\Bolt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\Security\Models\User;

class Page extends Model
{
    use SoftDeletes;

    protected $table = 'bolt_pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'published_at',
        'creator_id',
        'last_editor_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_editor_id');
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class, 'page_id')->orderBy('sort');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'page_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
