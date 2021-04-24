<?php

namespace App\Tests\Mocks;

use App\Exception\AuthenticationException;
use App\Exception\FailureResponseException;
use App\Exception\ValidationException;
use App\Model\Request\UserRegisterCredentialsDto;
use App\Model\Response\AuthenticationDataDto;
use App\Model\Response\BillingUserDto;
use App\Model\Response\ErrorResponseDto;
use App\Security\User;
use App\Service\AuthenticationClient;
use App\Service\BillingClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthenticationClientMock extends AuthenticationClient
{
    private $dataMock;

    public function __construct(
        DataMock $dataMock
    ) {
        $this->dataMock = $dataMock;
    }

    public function register(UserRegisterCredentialsDto $credentials): User
    {
        if(array_key_exists($credentials->getUsername(), $this->dataMock->registeredUsers)) {
            $details = [
                'email' => "User with email \"{$credentials->getUsername()}\" is already exists. Try to login instead",
            ];
            throw new ValidationException($details);
        }

        if ('1234567' === $credentials->getPassword()) {
            $details = [
                'email' => 'This password has been leaked in a data breach, it must not be used. Please use another password',
            ];
            throw new ValidationException($details);
        }

        $user = new User();
        $user->setEmail($credentials->getEmail());
        $user->setRoles(['ROLE_USER']);
        $user->setApiToken($this->dataMock->getJwt($user));
        $user->setRefreshToken('refresh');

        $this->dataMock->registeredUsers[$credentials->getUsername()] = $user;
        $this->dataMock->userBalance[$credentials->getUsername()] = 10000;

        return $user;
    }

    public function login(UserRegisterCredentialsDto $credentials): User
    {
        if (!array_key_exists($credentials->getUsername(), $this->dataMock->registeredUsers) ||
            'password' !== $credentials->getPassword())
        {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->dataMock->registeredUsers[$credentials->getUsername()];
    }

    public function updateJwt(User $user)
    {
        $this->dataMock->testUserValid($user);

        if($user->getRefreshToken() !== 'refresh') {
            $refreshError = new ErrorResponseDto();
            $refreshError->setCode(401);
            $refreshError->setMessage('An authentication exception occurred.');
            throw new AuthenticationException();
        }

        $newJwt = $this->dataMock->getJwt($user);
        $this->dataMock->registeredUsers[$user->getUsername()]->setApiToken($newJwt);

        $user->setApiToken($newJwt);
        $user->setRefreshToken('refresh');
    }
}
