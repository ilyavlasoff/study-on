<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

class AuthenticationDataDto
{
    /**
     * @Serializer\Type("string")
     */
    private $token;

    /**
     * @Serializer\Type("array")
     */
    private $roles;

    /**
     * @Serializer\SerializedName("refresh_token")
     * @Serializer\Type("string")
     */
    private $refreshToken;

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token): void
    {
        $this->token = $token;
    }

    /**
     * @return mixed
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param mixed $roles
     */
    public function setRoles($roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken($refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

}