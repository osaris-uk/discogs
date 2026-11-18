<?php

namespace Osaris\Discogs\Auth;

final class CredentialsFactory
{
    /**
     * Resolve the default credentials from the package config, or null when
     * no complete set is present.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): ?Credentials
    {
        $key = self::value($config, 'consumer_key');
        $secret = self::value($config, 'consumer_secret');
        $oauthToken = self::value($config, 'oauth_token');
        $oauthSecret = self::value($config, 'oauth_token_secret');
        $token = self::value($config, 'token');

        if ($key && $secret && $oauthToken && $oauthSecret) {
            return new OAuth($key, $secret, $oauthToken, $oauthSecret);
        }

        if ($token) {
            return new PersonalToken($token);
        }

        if ($key && $secret) {
            return new ConsumerKey($key, $secret);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function value(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
