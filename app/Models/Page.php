<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_description',
        'meta_keywords',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('sort_order');
    }
}
