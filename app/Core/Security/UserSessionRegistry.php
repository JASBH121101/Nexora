<?php

namespace App\Core\Security;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UserSessionRegistry
{
    public function register(Request $request, User $user): void
    {
        $sessionHash = $this->hash($request);

        $session = UserSession::query()->where('session_hash', $sessionHash)->first();

        if ($session !== null && (int) $session->user_id !== (int) $user->id) {
            throw new RuntimeException('The authenticated session identifier is already registered to another user.');
        }

        $session ??= new UserSession();

        $session->fill([
            'user_id' => $user->id,
            'session_hash' => $sessionHash,
            ...$this->metadata($request),
        ])->save();
    }

    public function rotate(Request $request, User $user, string $previousSessionId): void
    {
        $previousHash = hash('sha256', $previousSessionId);
        $currentHash = $this->hash($request);

        DB::transaction(function () use ($request, $user, $previousHash, $currentHash): void {
            $session = UserSession::query()
                ->where('session_hash', $previousHash)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return;
            }

            $session->fill([
                'session_hash' => $currentHash,
                ...$this->metadata($request),
            ])->save();
        });
    }

    public function touch(Request $request, User $user): bool
    {
        $expiredBefore = now()
            ->subMinutes(max(1, (int) config('session.lifetime', 120)))
            ->timestamp;

        $updated = UserSession::query()
            ->where('session_hash', $this->hash($request))
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $expiredBefore)
            ->update([
                ...$this->metadata($request),
                'last_activity' => now()->timestamp,
                'updated_at' => now(),
            ]);

        return $updated === 1;
    }

    public function revokeCurrent(Request $request, User $user): void
    {
        UserSession::query()
            ->where('session_hash', $this->hash($request))
            ->where('user_id', $user->id)
            ->delete();
    }

    public function revokePublicId(string $publicId): bool
    {
        return UserSession::query()->whereKey($publicId)->delete() === 1;
    }

    public function revokeOthers(Request $request, User $user): void
    {
        UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_hash', '!=', $this->hash($request))
            ->delete();
    }

    public function revokeUser(int $userId): int
    {
        return UserSession::query()->where('user_id', $userId)->delete();
    }

    public function pruneExpired(): int
    {
        $expiredBefore = now()
            ->subMinutes(max(1, (int) config('session.lifetime', 120)))
            ->timestamp;

        return UserSession::query()
            ->where('last_activity', '<', $expiredBefore)
            ->delete();
    }

    public function hash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function metadata(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'last_activity' => now()->timestamp,
        ];
    }
}
