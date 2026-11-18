<?php

namespace Osaris\Discogs\Auth;

use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * OAuth 1.0a credentials (PLAINTEXT signature, sent over HTTPS).
 * Acts as the user who granted the access token.
 */
final class OAuth implements Credentials
{
    public function __construct(
        private readonly string $consumerKey,
        #[SensitiveParameter] private readonly string $consumerSecret,
        private readonly string $token,
        #[SensitiveParameter] private readonly string $tokenSecret,
    ) {}

    public function authorizationHeader(): string
    {
        return self::header($this->consumerKey, $this->consumerSecret, $this->token, $this->tokenSecret);
    }

    public function actsAsUser(): bool
    {
        return true;
    }

    /**
     * Build an OAuth 1.0a PLAINTEXT Authorization header.
     *
     * @param  array<string, string>  $extra  Additional oauth_* parameters (oauth_callback, oauth_verifier).
     */
    public static function header(
        string $consumerKey,
        #[SensitiveParameter] string $consumerSecret,
        ?string $token = null,
        #[SensitiveParameter] ?string $tokenSecret = null,
        array $extra = [],
    ): string {
        $params = array_filter([
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => Str::random(32),
            'oauth_token' => $token,
            'oauth_signature' => $consumerSecret.'&'.($tokenSecret ?? ''),
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_timestamp' => (string) time(),
        ] + $extra, fn ($value) => $value !== null);

        $parts = [];

        foreach ($params as $name => $value) {
            // The PLAINTEXT signature is sent as-is ("secret&secret"), as in the Discogs docs.
            $parts[] = $name.'="'.($name === 'oauth_signature' ? $value : rawurlencode($value)).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }
}
