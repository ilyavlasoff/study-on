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

}