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

        if ($session !== null) {
            if ((int) $session->user_id !== (int) $user->id || $session->revoked_at !== null) {
                throw new RuntimeException('The session identifier is already registered or revoked.');
            }

            $session->fill($this->metadata($request))->save();

            return;
        }

        UserSession::query()->create([
            'user_id' => $user->id,
            'session_hash' => $sessionHash,
            ...$this->metadata($request),
        ]);
    }

    public function rotate(Request $request, User $user, string $previousSessionId): void
    {
        $previousHash = hash('sha256', $previousSessionId);
        $currentHash = $this->hash($request);

        DB::transaction(function () use ($request, $user, $previousHash, $currentHash): void {
            $session = UserSession::query()
                ->where('session_hash', $previousHash)
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
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
        $sessionHash = $this->hash($request);
        $expiredBefore = now()
            ->subMinutes(max(1, (int) config('session.lifetime', 120)))
            ->timestamp;

        $session = UserSession::query()
            ->where('session_hash', $sessionHash)
            ->where('user_id', $user->id)
            ->first();

        if ($session === null) {
            // Register pre-existing sessions on first use; this also supports the
            // configured session driver and Laravel's authenticated test guard.
            try {
                $this->register($request, $user);
            } catch (RuntimeException) {
                return false;
            }

            return true;
        }

        if ($session->revoked_at !== null) {
            return false;
        }

        if ($session->last_activity < $expiredBefore) {
            $session->forceFill(['revoked_at' => now()])->save();

            return false;
        }

        $session->forceFill([
            ...$this->metadata($request),
        ])->save();

        return true;
    }

    public function revokeCurrent(Request $request, User $user): void
    {
        UserSession::query()
            ->where('session_hash', $this->hash($request))
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function revokePublicId(string $publicId): bool
    {
        return UserSession::query()
            ->whereKey($publicId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) === 1;
    }

    public function revokeOthers(Request $request, User $user): void
    {
        UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_hash', '!=', $this->hash($request))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function revokeUser(int $userId): int
    {
        return UserSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function pruneExpired(): int
    {
        $expiredBefore = now()
            ->subMinutes(max(1, (int) config('session.lifetime', 120)))
            ->timestamp;

        return UserSession::query()
            ->whereNull('revoked_at')
            ->where('last_activity', '<', $expiredBefore)
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
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
