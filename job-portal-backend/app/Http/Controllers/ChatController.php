<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Events\NewMessage;
use App\Events\MessageRead;
use App\Events\MessageDeletedForEveryone;
use Illuminate\Support\Facades\DB;


class ChatController extends Controller
{
    /**
     * Get conversation list with filters: all, archived, unread
     */
    public function getConversations(Request $request)
    {
        $userId = $request->user()->id;
        $filter = $request->query('filter', 'all'); // default = all

        $query = Conversation::forUser($userId)
            ->with(['user1:id,email',
                    'user1.profile:id,user_id,full_name,email',
                    'user1.organization:id,user_id,name,email',
                    'user2:id,email',
                    'user2.profile:id,user_id,full_name,email',
                    'user2.organization:id,user_id,name,email',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->where(function($query) use ($userId) {
                    $query->whereNull('read_at')
                          ->orWhere(function($q2) use ($userId) {
                              $q2->whereRaw('(conversations.user1_id = ? AND user1_last_read_at IS NULL)', [$userId])
                                 ->orWhereRaw('(conversations.user2_id = ? AND user2_last_read_at IS NULL)', [$userId]);
                          });
                });
            }])
            ->orderBy('updated_at', 'desc');

        // Apply filters
        if ($filter === 'archived') {
            $query->archived($userId);
        } elseif ($filter === 'unread') {
            $query->whereHas('messages', function ($q) use ($userId) {
                $q->whereNull('read_at')
                    ->where('sender_id', '!=', $userId)
                    ->where(function ($subQ) use ($userId) {
                        $subQ->whereRaw('(conversations.user1_id = ? AND deleted_by_user1 = false)', [$userId])
                            ->orWhereRaw('(conversations.user2_id = ? AND deleted_by_user2 = false)', [$userId]);
                    });
            });
        }

        // Only include conversations with at least one message visible to the user
        $query->whereHas('messages', function ($q) use ($userId) {
            $q->where(function ($q2) use ($userId) {
                $q2->when($userId, function ($q3) use ($userId) {
                    $q3->where(function ($q4) use ($userId) {
                        $q4->where('deleted_by_user1', false)
                           ->whereRaw('conversations.user1_id = ?', [$userId]);
                    })->orWhere(function ($q4) use ($userId) {
                        $q4->where('deleted_by_user2', false)
                           ->whereRaw('conversations.user2_id = ?', [$userId]);
                    });
                });
            });
        });

        $conversations = $query->get()
            ->map(function ($conversation) use ($userId) {
                // Get the other participant
                $otherUser = $conversation->user1_id === $userId 
                    ? $conversation->user2 
                    : $conversation->user1;

                // Pick correct display email
                $displayEmail = $otherUser->organization->email ?? 
                                $otherUser->profile->email ?? 
                                $otherUser->email;

                // Pick correct display name
                $displayName = $otherUser->organization 
                    ? $otherUser->organization->name 
                    : ($otherUser->profile->full_name ?? $otherUser->email);

                // Get last message (not deleted for current user)
                $lastMessage = Message::where('conversation_id', $conversation->id)
                    ->when($conversation->user1_id === $userId, fn($q) => $q->where('deleted_by_user1', false))
                    ->when($conversation->user2_id === $userId, fn($q) => $q->where('deleted_by_user2', false))
                    ->latest('created_at') // ensures newest message first
                    ->first();
                
                // Alternative approach using relationship (commented out)
                // $lastMessage = $conversation->messages()
                //     ->when($conversation->user1_id === $userId, fn($q) => $q->where('deleted_by_user1', false))
                //     ->when($conversation->user2_id === $userId, fn($q) => $q->where('deleted_by_user2', false))
                //     ->reorder('created_at', 'desc') // <-- important
                //     ->first();

                return [
                    'id' => $conversation->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'email' => $displayEmail,
                        'name' => $displayName,
                    ],
                    'is_archived' => $conversation->isArchivedFor($userId),
                    'unread_count' => $conversation->unread_count,
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'message' => $lastMessage->message,
                        'type' => $lastMessage->type,
                        'sender_id' => $lastMessage->sender_id,
                        'created_at' => $lastMessage->created_at,
                        'read_at' => $lastMessage->read_at,
                    ] : null,
                    'updated_at' => $conversation->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Mark conversation as unread (conversation-level) // NEW
     */
    public function markConversationAsUnread(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user1_id === $user->id) {
            $conversation->user1_last_read_at = null;
        } elseif ($conversation->user2_id === $user->id) {
            $conversation->user2_last_read_at = null;
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation->save();

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as unread'
        ]);
    }

    /**
     * Start a new conversation or return existing one.
     */
    public function startConversation(Request $request, $recipientId)
    {
        $userId = $request->user()->id;

        if ($userId == $recipientId) {
            return response()->json(['error' => 'You cannot start a conversation with yourself.'], 400);
        }

        // Always store smaller ID first to avoid duplicates
        [$u1, $u2] = $userId < $recipientId ? [$userId, $recipientId] : [$recipientId, $userId];

        $conversation = Conversation::firstOrCreate([
            'user1_id' => $u1,
            'user2_id' => $u2,
        ]);

        $conversation->load([
            'user1:id,email',
            'user1.profile:id,user_id,full_name,email',
            'user1.organization:id,user_id,name,email',
            'user2:id,email',
            'user2.profile:id,user_id,full_name,email',
            'user2.organization:id,user_id,name,email',
        ]);

        
        $otherUser = $conversation->user1_id === $userId 
            ? $conversation->user2 
            : $conversation->user1;

        $displayEmail = $otherUser->organization->email ?? 
                        $otherUser->profile->email ?? 
                        $otherUser->email;

        $displayName = $otherUser->organization 
            ? $otherUser->organization->name 
            : ($otherUser->profile->full_name ?? $otherUser->email);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'email' => $displayEmail,
                    'name' => $displayName,
                ],
            ],
        ]);

        
    }

    /**
     * Get all messages for a conversation and mark unread messages as read.
     */
    public function getMessages(Request $request, $conversationId)
    {
        $user = $request->user();
        $userId = $user->id;
        
        $conversation = Conversation::findOrFail($conversationId);

        // Ensure user is part of conversation
        if (!$conversation->hasParticipant($userId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Mark unread messages as read (messages sent by other user)
        $unreadMessages = $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->when($conversation->user1_id === $userId, fn($q) => $q->where('deleted_by_user1', false))
            ->when($conversation->user2_id === $userId, fn($q) => $q->where('deleted_by_user2', false))
            ->get();

        // Update read_at for all unread messages
        $now = now();
        $conversation->messages()
            ->whereIn('id', $unreadMessages->pluck('id'))
            ->update(['read_at' => $now]);

        // Broadcast the "message-read" event for each updated message
        foreach ($unreadMessages as $message) {
            $message->read_at = $now; // make sure read_at is set for broadcast
            broadcast(new MessageRead($message))->toOthers();
        }

        // Filter deleted messages for current user
        $messages = $conversation->messages()
            ->when($conversation->user1_id === $userId, fn($q) => $q->where('deleted_by_user1', false))
            ->when($conversation->user2_id === $userId, fn($q) => $q->where('deleted_by_user2', false))
            ->with(['sender.organization', 'sender.profile'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                $sender = $message->sender;
                $displayEmail = $sender->organization->email ?? $sender->profile->email ?? $sender->email;
                $displayName = $sender->organization->name ?? $sender->profile->full_name ?? 'Unknown';
                
                return [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'message' => $message->message,
                    'type' => $message->type,
                    'payload' => $message->payload,
                    'sender' => [
                        'id' => $sender->id,
                        'name' => $displayName,
                        'email' => $displayEmail,
                    ],
                    'created_at' => $message->created_at,
                    'read_at' => $message->read_at,
                ];
            });

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a new message in a conversation.
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required_without:payload|string|max:5000',
            'type' => 'sometimes|in:user_message,status_update,system',
            'payload' => 'sometimes|array', // For file attachments, etc.
        ]);

        $userId = $request->user()->id;
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        // Ensure user is part of the conversation
        if (!in_array($userId, [$conversation->user1_id, $conversation->user2_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Unarchive conversation if it was archived
        if ($conversation->user1_id === $userId) {
            $conversation->is_archived_by_user1 = false;
        } else {
            $conversation->is_archived_by_user2 = false;
        }

        $conversation->save(); // Save any changes to flags        

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message' => $validated['message'] ?? null,
            'type' => $validated['type'] ?? 'user_message',
            'payload' => $validated['payload'] ?? null,
        ]);

        // Update conversation timestamp
        $conversation->touch();

        // Load sender with organization and profile
        $sender = $message->sender->load(['organization', 'profile']);

        // Pick correct email: organization → profile → user
        $displayEmail = $sender->organization->email 
            ?? $sender->profile->email 
            ?? $sender->email;
            
        // Broadcast in real time
        broadcast(new NewMessage($message))->toOthers();        

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'message' => $message->message,
                'type' => $message->type,
                'payload' => $message->payload,
                'sender' => [
                    'id' => $sender->id,
                    'email' => $displayEmail,
                ],
                'created_at' => $message->created_at,
                'read_at' => $message->read_at,
            ]
        ], 201);
    }

    /**
     * Get unread message count for user.
     */
    public function getUnreadCount(Request $request)
    {
        $userId = $request->user()->id;

        $unreadCount = Message::whereHas('conversation', function ($query) use ($userId) {
                $query->forUser($userId);
            })
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->whereHas('conversation', function ($query) use ($userId) {
                $query->when($userId, function ($q) use ($userId) {
                    $q->where(function ($subQuery) use ($userId) {
                        $subQuery->where('user1_id', $userId)->where('deleted_by_user1', false)
                                ->orWhere('user2_id', $userId)->where('deleted_by_user2', false);
                    });
                });
            })
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Archive a conversation (for current user only).
     */
    public function archiveConversation(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user1_id === $user->id) {
            $conversation->update(['is_archived_by_user1' => true]);
        } elseif ($conversation->user2_id === $user->id) {
            $conversation->update(['is_archived_by_user2' => true]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'message' => 'Conversation archived']);
    }

    /**
     * Unarchive a conversation.
     */
    public function unarchiveConversation(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user1_id === $user->id) {
            $conversation->update(['is_archived_by_user1' => false]);
        } elseif ($conversation->user2_id === $user->id) {
            $conversation->update(['is_archived_by_user2' => false]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'message' => 'Conversation unarchived']);
    }

    /**
     * Delete a conversation (soft delete for current user).
     */
    public function deleteConversation(Request $request, $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!in_array($user->id, [$conversation->user1_id, $conversation->user2_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Mark messages as deleted for the user
        $field = $conversation->user1_id === $user->id ? 'deleted_by_user1' : 'deleted_by_user2';
        $conversation->messages()->update([$field => true]);

        return response()->json(['success' => true, 'message' => 'Conversation deleted for you']);
    }

    /**
     * Delete a single message for current user only
     */
    public function deleteMessageForMe(Request $request, Message $message)
    {
        $user = $request->user();
        $conversation = $message->conversation;

        if (!in_array($user->id, [$conversation->user1_id, $conversation->user2_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $field = $conversation->user1_id === $user->id ? 'deleted_by_user1' : 'deleted_by_user2';
        $message->update([$field => true]);

        return response()->json(['success' => true, 'message' => 'Message deleted for you']);
    }

    /**
     * Delete a single message for everyone (only if unread and sender)
     */
    public function deleteMessageForEveryone(Request $request, Message $message)
    {
        $user = $request->user();

        if ($message->sender_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!is_null($message->read_at)) {
            return response()->json(['error' => 'Cannot delete message for everyone after it has been read'], 403);
        }

    DB::transaction(function () use ($message) {
        // Soft delete the message (requires $table->softDeletes() in migration)
        $message->delete();
        $message->refresh(); // ensures $message->deleted_at is populated
        // Broadcast the deletion event to other users
        broadcast(new MessageDeletedForEveryone($message))->toOthers();
    });

        return response()->json([
            'success' => true, 
            'message' => 'Message deleted for everyone',
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id
        ]);
    }
}