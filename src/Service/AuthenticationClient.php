<?php

namespace App\Service;

use App\Exception\AuthenticationException;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Model\AuthenticationDataDto;
use App\Model\UserRegisterCredentialsDto;
use App\Security\User;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;

class AuthenticationClient
{
    private $billingClient;
    private $serializer;

    public function __construct(BillingClient $billingClient, SerializerInterface $serializer)
    {
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;
    }

    /**
     * @param UserRegisterCredentialsDto $credentials
     * @return User
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function register(UserRegisterCredentialsDto $credentials): User
    {
        $userCredentials = $this->serializer->serialize(
            $credentials,
            'json',
            SerializationContext::create()->setGroups(['reg'])
        );

        $registeredClientResponse = $this->billingClient->billingRequest(
            'POST',
            '/register',
            $userCredentials,
            null
        );

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize(
            $registeredClientResponse,
            AuthenticationDataDto::class,
            'json'
        );
        return User::createFromDto($authenticationData);
    }

    /**
     * @param UserRegisterCredentialsDto $credentials
     * @return User
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function login(UserRegisterCredentialsDto $credentials): User
    {
        $userCredentials = $this->serializer->serialize(
            $credentials,
            'json',
            SerializationContext::create()->setGroups(['auth'])
        );

        $loggedInClientResponse = $this->billingClient->billingRequest(
            'POST',
            '/auth',
            $userCredentials,
            null
        );

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize(
            $loggedInClientResponse,
            AuthenticationDataDto::class,
            'json'
        );

        return User::createFromDto($authenticationData);
    }

    /**
     * @param User $user
     * @return User
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function updateJwt(User $user): User
    {
        $userOldData =$this->serializer->serialize($user, 'json');
        $updatedUserResponse = $this->billingClient->billingRequest(
            'POST',
            '/token/refresh',
            $userOldData,
            $user->getApiToken()
        );

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize(
            $updatedUserResponse,
            AuthenticationDataDto::class,
            'json'
        );
        return User::createFromDto($authenticationData);
    }
}
