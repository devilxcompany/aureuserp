<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    public const TYPES = [
        'hero' => 'Hero',
        'text' => 'Text',
        'image' => 'Image',
        'cta' => 'Call to Action',
        'testimonial' => 'Testimonial',
        'faq' => 'FAQ',
        'gallery' => 'Gallery',
        'video' => 'Video',
    ];

    protected $fillable = [
        'page_id',
        'name',
        'type',
        'content',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function getTypes(): array
    {
        return self::TYPES;
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
