<?php

namespace App\Service;

class JwtDecoder
{
    public static function decode(String $jwt)
    {
        $tokenParts = explode(".", $jwt);
        return json_decode(base64_decode($tokenParts[1]), true);
    }

    public static function extractUsername(String $jwt): string
    {
        return (self::decode($jwt))['username'] ?? null;
    }

    public static function extractExpires(String $jwt): \DateTime
    {
        $decodedJwt = self::decode($jwt);
        return  isset($decodedJwt['exp']) ? (new \DateTime())->setTimestamp($decodedJwt['exp']) :
            (new \DateTime())->add(\DateInterval::createFromDateString('-1 day'));
    }

    public static function extractRoles(String $jwt): array
    {
        return (self::decode($jwt))['roles'] ?? [];
    }
}
