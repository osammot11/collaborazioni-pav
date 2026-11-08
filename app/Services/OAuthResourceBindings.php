<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class OAuthResourceBindings
{
    public static function remember(string $kind, string $credential, string $resource, DateTimeInterface $expiresAt): void
    {
        // Never store authorization codes or refresh tokens in plaintext.
        DB::table('oauth_resource_bindings')->updateOrInsert(
            ['credential_hash' => hash('sha256', $kind.':'.$credential)],
            ['resource' => $resource, 'expires_at' => $expiresAt],
        );
    }

    public static function resource(string $kind, string $credential): ?string
    {
        return DB::table('oauth_resource_bindings')
            ->where('credential_hash', hash('sha256', $kind.':'.$credential))
            ->where('expires_at', '>', now())->value('resource');
    }
}
