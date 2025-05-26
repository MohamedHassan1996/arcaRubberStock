<?php

namespace Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    protected static string $secret;
    protected static string $algo;
    protected static int $ttl;
    protected static string $blacklistFile;

    // Initialize configuration
    public static function init(): void
    {
        $config = require __DIR__ . '/../config/jwt.php';

        self::$secret = $config['secret'];
        self::$algo   = $config['algo'];
        self::$ttl    = $config['ttl'];

        // Define the blacklist file path
        self::$blacklistFile = __DIR__ . '/../storage/jwt_blacklist.json';

        // Ensure the storage directory exists
        $storageDir = dirname(self::$blacklistFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);  // Recursive mkdir with permissions
        }

        // Create the blacklist file if it does not exist
        if (!file_exists(self::$blacklistFile)) {
            file_put_contents(self::$blacklistFile, json_encode([]));
        }
    }

    // Generate token with unique jti
    public static function make(array $payload): string
    {
        self::init();

        $issuedAt = time();
        $expires  = $issuedAt + self::$ttl;

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expires;
        $payload['jti'] = bin2hex(random_bytes(16)); // unique token id

        return FirebaseJWT::encode($payload, self::$secret, self::$algo);
    }

    // Decode token and check blacklist
    public static function verify(string $token): ?object
    {
        self::init();

        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::$secret, self::$algo));

            if (self::isBlacklisted($decoded->jti ?? null)) {
                // Token is blacklisted
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    // Add token jti to blacklist
public static function destroy(): bool
{
    self::init();

    try {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            // Just decode once here, no need to call verify()
            $decoded = FirebaseJWT::decode($matches[1], new Key(self::$secret, self::$algo));
        } else {
            return false; // No token found
        }

        $jti = $decoded->jti ?? null;
        $exp = $decoded->exp ?? null;

        if (!$jti || !$exp) {
            return false;
        }

        $blacklist = self::getBlacklist();

        // Add jti with expiration timestamp
        $blacklist[$jti] = $exp;

        self::saveBlacklist($blacklist);

        return true;
    } catch (\Exception $e) {
        return false;
    }
}

    // Check Authorization header
    public static function check(): ?object
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return self::verify($matches[1]);
        }

        return null;
    }

    // Check if jti is blacklisted (and clean expired blacklist entries)
    protected static function isBlacklisted(?string $jti): bool
    {
        if (!$jti) {
            return false;
        }

        $blacklist = self::getBlacklist();

        // Clean expired tokens from blacklist
        $now = time();
        foreach ($blacklist as $tokenJti => $exp) {
            if ($exp < $now) {
                unset($blacklist[$tokenJti]);
            }
        }
        self::saveBlacklist($blacklist);

        return isset($blacklist[$jti]);
    }

    // Load blacklist from storage
    protected static function getBlacklist(): array
    {
        $data = file_get_contents(self::$blacklistFile);
        return json_decode($data, true) ?: [];
    }

    // Save blacklist to storage
    protected static function saveBlacklist(array $blacklist): void
    {
        file_put_contents(self::$blacklistFile, json_encode($blacklist));
    }
}
