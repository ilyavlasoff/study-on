<?php

namespace App\Model\Request;

use JMS\Serializer\Annotation as Serializer;

class UserRegisterCredentialsDto
{
    /**
     * @Serializer\Type("string")
     * @Serializer\Groups({"reg"})
     */
    private $email;

    /**
     * @Serializer\Type("string")
     * @Serializer\Groups({"reg", "auth"})
     */
    private $password;

    /**
     * @Serializer\VirtualProperty
     * @Serializer\Type("string")
     * @Serializer\SerializedName("username")
     * @Serializer\Groups({"auth"})
     */
    public function getUsername()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }
}
