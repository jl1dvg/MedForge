<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationNote;
use App\Models\WhatsappQuickReply;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProductivityToolkitService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listQuickReplies(string $search = '', int $limit = 25): array
    {
        if (!Schema::hasTable('whatsapp_quick_replies')) {
            return [];
        }

        $query = WhatsappQuickReply::query()
            ->where('is_active', true)
            ->orderBy('title');

        $search = trim($search);
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', '%' . $search . '%')
                    ->orWhere('shortcut', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%');
            });
        }

        return $query
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (WhatsappQuickReply $reply): array => [
                'id' => (int) $reply->id,
                'title' => (string) $reply->title,
                'shortcut' => $reply->shortcut !== null ? (string) $reply->shortcut : null,
                'body' => (string) $reply->body,
                'created_by_user_id' => $reply->created_by_user_id,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createQuickReply(string $title, string $body, ?string $shortcut, ?int $actorUserId): array
    {
        if (!Schema::hasTable('whatsapp_quick_replies')) {
            throw new RuntimeException('La tabla de respuestas rápidas aún no está disponible.');
        }

        $title = trim($title);
        $body = trim($body);
        $shortcut = $this->normalizeShortcut($shortcut);

        if ($title === '') {
            throw new RuntimeException('El título de la respuesta rápida es obligatorio.');
        }

        if ($body === '') {
            throw new RuntimeException('El contenido de la respuesta rápida es obligatorio.');
        }

        $reply = WhatsappQuickReply::query()->create([
            'title' => mb_substr($title, 0, 120),
            'shortcut' => $shortcut,
            'body' => $body,
            'created_by_user_id' => $actorUserId,
            'is_active' => true,
        ]);

        return [
            'id' => (int) $reply->id,
            'title' => (string) $reply->title,
            'shortcut' => $reply->shortcut,
            'body' => (string) $reply->body,
            'created_by_user_id' => $reply->created_by_user_id,
            'source' => 'laravel-v2',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversationNotes(int $conversationId, int $limit = 20): array
    {
        if (!Schema::hasTable('whatsapp_conversation_notes')) {
            return [];
        }

        $authorIds = WhatsappConversationNote::query()
            ->where('conversation_id', $conversationId)
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->pluck('author_user_id')
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $authors = [];
        if ($authorIds !== [] && Schema::hasTable('users')) {
            $authors = User::query()
                ->whereIn('id', $authorIds)
                ->get()
                ->mapWithKeys(function (User $user): array {
                    $name = trim((string) $user->nombre);
                    if ($name === '') {
                        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);
                    }
                    if ($name === '') {
                        $name = (string) $user->username;
                    }

                    return [(int) $user->id => $name];
                })
                ->all();
        }

        return WhatsappConversationNote::query()
            ->where('conversation_id', $conversationId)
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (WhatsappConversationNote $note): array => [
                'id' => (int) $note->id,
                'body' => (string) $note->body,
                'author_user_id' => $note->author_user_id,
                'author_name' => $note->author_user_id !== null ? ($authors[(int) $note->author_user_id] ?? ('Usuario #' . $note->author_user_id)) : null,
                'created_at' => optional($note->created_at)?->toISOString(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function addConversationNote(int $conversationId, string $body, ?int $actorUserId): array
    {
        if (!Schema::hasTable('whatsapp_conversation_notes')) {
            throw new RuntimeException('La tabla de notas internas aún no está disponible.');
        }

        if (!Schema::hasTable('whatsapp_conversations') || !WhatsappConversation::query()->whereKey($conversationId)->exists()) {
            throw new RuntimeException('Conversación no encontrada.');
        }

        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException('La nota interna no puede estar vacía.');
        }

        $note = WhatsappConversationNote::query()->create([
            'conversation_id' => $conversationId,
            'author_user_id' => $actorUserId,
            'body' => $body,
        ]);

        return [
            'id' => (int) $note->id,
            'conversation_id' => (int) $note->conversation_id,
            'body' => (string) $note->body,
            'author_user_id' => $note->author_user_id,
            'created_at' => optional($note->created_at)?->toISOString(),
            'source' => 'laravel-v2',
        ];
    }

    private function normalizeShortcut(?string $shortcut): ?string
    {
        $shortcut = trim((string) $shortcut);
        if ($shortcut === '') {
            return null;
        }

        return mb_strtolower(mb_substr($shortcut, 0, 64));
    }
}
