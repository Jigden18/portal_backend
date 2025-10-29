<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user1_id', 'user2_id', 'is_archived_by_user1', 'is_archived_by_user2'];

    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
    
    // Get both participants as a collection
    public function participants()
    {
        return $this->belongsToMany(User::class, 'users', 'id', 'id')
            ->whereIn('id', [$this->user1_id, $this->user2_id]);
    }

    // SCOPE to get all conversations involving a specific user
    public function scopeForUser($query, $userId) 
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
        });
    }

    /**
     * Helper: Check if a user is part of this conversation
     */
    public function hasParticipant($userId)
    {
        return in_array($userId, [$this->user1_id, $this->user2_id]);
    }

    // NEW FILTER SCOPES
    public function scopeArchived($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($qq) use ($userId) {
                $qq->where('user1_id', $userId)->where('is_archived_by_user1', true);
            })->orWhere(function ($qq) use ($userId) {
                $qq->where('user2_id', $userId)->where('is_archived_by_user2', true);
            });
        });
    }

    public function scopeActive($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($qq) use ($userId) {
                $qq->where('user1_id', $userId)->where('is_archived_by_user1', false);
            })->orWhere(function ($qq) use ($userId) {
                $qq->where('user2_id', $userId)->where('is_archived_by_user2', false);
            });
        });
    }

    /**
     * Check if a conversation exists between two users and return it
     */
    public static function between($userA, $userB)
    {
        [$u1, $u2] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
        return self::where('user1_id', $u1)->where('user2_id', $u2)->first();
    }

    /**
     * Get or create a normalized conversation
     */
    public static function findOrCreateBetween($userA, $userB)
    {
        [$u1, $u2] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
        return self::firstOrCreate(['user1_id' => $u1, 'user2_id' => $u2]);
    }

    // Check if conversation is archived for a specific user
    public function isArchivedFor($userId) 
    {
        return $this->user1_id === $userId ? $this->is_archived_by_user1 : $this->is_archived_by_user2;
    }
}