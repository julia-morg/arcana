<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_SENT = 'SENT';
    public const STATUS_PREPARED = 'PREPARED';
    protected $fillable = [
        'direction',
        'status',
        'chat_id',
        'user_id',
        'username',
        'message',
        'is_command',
        'parent_message_id',
        'external_id',
        'received_at',
    ];

    protected $dates = [
        'received_at',
    ];
}
