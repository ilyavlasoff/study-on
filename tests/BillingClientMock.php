<?php

namespace App\Tests;

use App\Exception\AuthenticationException;
use App\Exception\FailureResponseException;
use App\Model\AuthenticationErrorDto;
use App\Model\BillingUserDto;
use App\Model\FailureResponseDto;
use App\Model\UserRegisterCredentialsDto;
use App\Security\User;
use App\Service\BillingClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClientMock extends BillingClient
{
    private $registeredUsers;
    private $tokenStorage;

    public function __construct(
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage
    ) {
        parent::__construct($billingUrlBase, $billingApiVersion, $httpClient, $serializer, $tokenStorage);
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setApiToken('password');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);

        $user = new User();
        $user->setEmail('user@test.com');
        $user->setApiToken('password');
        $user->setRoles(['ROLE_USER']);
        $this->registeredUsers = [$admin, $user];
        $this->tokenStorage = $tokenStorage;
    }

    public function register(UserRegisterCredentialsDto $credentials): User
    {
        if (count(array_values(array_filter($this->registeredUsers, static function (User $user) use ($credentials) {
            return $user->getEmail() === $credentials->getEmail();
        })))) {
            $failureResponse = new FailureResponseDto();
            $failureResponse->setError(['User with email "user@test.com" is already exists. Try to login instead']);
            throw new FailureResponseException($failureResponse);
        }

        if ($credentials->getPassword() === '1234567') {
            $failureResponse = new FailureResponseDto();
            $failureResponse->setError(['This password has been leaked in a data breach, it must not be used. Please use another password']);
            throw new FailureResponseException($failureResponse);
        }

        $user = new User();
        $user->setEmail($credentials->getEmail());
        $user->setApiToken($credentials->getPassword());
        $user->setRoles(['ROLE_USER']);
        $this->registeredUsers[] = $user;
        return $user;
    }

    public function login(UserRegisterCredentialsDto $credentials): User
    {
        $foundedUsers = array_values(array_filter($this->registeredUsers, static function (User $user) use ($credentials) {
            return $user->getApiToken() === $credentials->getPassword()
                && $user->getEmail() === $credentials->getEmail();
        }));

        if (count($foundedUsers) === 0) {
            $authenticationError = new AuthenticationErrorDto();
            $authenticationError->setCode(1);
            $authenticationError->setMessage('Invalid credentials');
            throw new AuthenticationException($authenticationError);
        }
        return $foundedUsers[0];
    }

    public function currentClient(): BillingUserDto
    {
        $user = $this->tokenStorage->getToken()->getUser();
        $billingUser = new BillingUserDto();
        $billingUser->setUsername($user->getUsername());
        $billingUser->setRoles($user->getRoles());
        $billingUser->setBalance(101);
        return $billingUser;
    }
}
