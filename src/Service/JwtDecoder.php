<?php

namespace App\Service;

class JwtDecoder
{
    public static function decode(string $jwt)
    {
        $tokenParts = explode('.', $jwt);

        return json_decode(base64_decode($tokenParts[1]), true);
    }

    public static function extractUsername(string $jwt)
    {
        return (self::decode($jwt))['username'] ?? null;
    }

    public static function extractExpires(string $jwt): \DateTime
    {
        $decodedJwt = self::decode($jwt);

        return isset($decodedJwt['exp']) ? (new \DateTime())->setTimestamp($decodedJwt['exp']) :
            (new \DateTime())->add(\DateInterval::createFromDateString('-1 day'));
    }

    public static function extractRoles(string $jwt): array
    {
        return (self::decode($jwt))['roles'] ?? [];
    }
}
