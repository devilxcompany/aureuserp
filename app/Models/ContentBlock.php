<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'content',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_content_block')
            ->withPivot('sort_order');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public static function getTypes(): array
    {
        return [
            'hero' => 'Hero Banner',
            'testimonial' => 'Testimonials',
            'cta' => 'Call to Action',
            'text' => 'Text Block',
            'image' => 'Image Block',
            'gallery' => 'Gallery',
            'faq' => 'FAQ',
            'features' => 'Features List',
        ];
    }
}
