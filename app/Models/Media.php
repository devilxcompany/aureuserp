<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'name',
        'file_name',
        'mime_type',
        'disk',
        'path',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
