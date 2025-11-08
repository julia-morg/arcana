<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meme extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'post_id',
        'source_url',
        'image_path',
        'caption',
        'image_extension',
        'image_mime',
    ];
}


