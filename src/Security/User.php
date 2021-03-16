<?php

namespace App\Security;

use App\Model\AuthenticationDataDto;
use App\Model\BillingUserDto;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $email;

    private $roles = [];

    private $apiToken;

    public static function createFromDto(AuthenticationDataDto $authenticationDataDto)
    {
        $user = new self;
        $user->setRoles($authenticationDataDto->getRoles());
        $user->setApiToken($authenticationDataDto->getToken());

        $tokenParts = explode(".", $authenticationDataDto->getToken());
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        $username = $payload['username'];
        $user->setEmail($username);

        return $user;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getApiToken()
    {
        return $this->apiToken;
    }

    /**
     * @param mixed $apiToken
     */
    public function setApiToken($apiToken): void
    {
        $this->apiToken = $apiToken;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * This method is not needed for apps that do not check user passwords.
     *
     * @see UserInterface
     */
    public function getPassword(): ?string
    {
        return null;
    }

    /**
     * This method is not needed for apps that do not check user passwords.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
