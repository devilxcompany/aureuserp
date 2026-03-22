<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $fillable = [
        'name',
        'description',
        'fields',
        'is_active',
        'submissions_enabled',
        'recipient_email',
        'confirmation_message',
        'redirect_url',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean',
        'submissions_enabled' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $form) {
            if (empty($form->slug)) {
                $form->slug = static::generateUniqueSlug($form->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$count++;
        }

        return $slug;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
