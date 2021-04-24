<?php

namespace App\Security;

use App\Model\Response\AuthenticationDataDto;
use App\Service\JwtDecoder;
use JMS\Serializer\Annotation as JMS;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class User
 * @JMS\ExclusionPolicy("all")
 */
class User implements UserInterface
{
    /**
     * @var string
     */
    private $email;

    /**
     * @var string[]
     */
    private $roles = [];

    /**
     * @var string
     */
    private $apiToken;

    /**
     * @var string
     * @JMS\Expose()
     */
    private $refreshToken;

    public static function createFromDto(AuthenticationDataDto $authenticationDataDto): self
    {
        $user = new self();
        $user->setRoles($authenticationDataDto->getRoles());
        $user->setApiToken($authenticationDataDto->getToken());
        $user->setEmail(JwtDecoder::extractUsername($authenticationDataDto->getToken()));
        $user->setRefreshToken($authenticationDataDto->getRefreshToken());

        return $user;
    }
    
    public function updateTokensWithDto(AuthenticationDataDto $authenticationDataDto)
    {
        $this->refreshToken = $authenticationDataDto->getRefreshToken();
        $this->apiToken = $authenticationDataDto->getToken();
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
        $this->email = null;
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
