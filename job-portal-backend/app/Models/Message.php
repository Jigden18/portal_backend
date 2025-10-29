<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'type',
        'payload',
        'read_at',
        'deleted_by_user1',
        'deleted_by_user2',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'deleted_by_user1' => 'boolean',
        'deleted_by_user2' => 'boolean',
    ];

    /** Relationships */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Helpers */
    public function markAsRead() 
    {
        $this->update(['read_at' => now()]);
    }

    /** Scopes */
    public function scopeAutomated($query)
    {
        return $query->where('type', 'status_update');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // Visible messages for a user (respecting deleted flags)
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($sub) use ($userId) {
                $sub->whereHas('conversation', fn($c) => $c->where('user1_id', $userId))
                    ->where('deleted_by_user1', false);
            })
            ->orWhere(function ($sub) use ($userId) {
                $sub->whereHas('conversation', fn($c) => $c->where('user2_id', $userId))
                    ->where('deleted_by_user2', false);
            });
        });
    }

    // Unread messages for user
    public function scopeUnreadForUser($query, $userId)
    {
        return $query->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->whereHas('conversation', function ($q) use ($userId) {
                $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
            });
    }
}
