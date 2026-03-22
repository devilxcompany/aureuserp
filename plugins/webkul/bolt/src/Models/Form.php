<?php

namespace Webkul\Bolt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\Security\Models\User;

class Form extends Model
{
    use SoftDeletes;

    protected $table = 'bolt_forms';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'fields',
        'submit_button_label',
        'success_message',
        'notification_emails',
        'submissions_enabled',
        'creator_id',
    ];

    protected $casts = [
        'fields'              => 'array',
        'submissions_enabled' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }
}
