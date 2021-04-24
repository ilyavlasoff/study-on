<?php

namespace App\Service;

use App\Exception\AuthenticationException;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Exception\ValidationException;
use App\Model\Request\UserRegisterCredentialsDto;
use App\Model\Response\AuthenticationDataDto;
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
     *
     * @return User
     *
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

        try {
            $registeredClientResponse = $this->billingClient->billingRequest(
                'POST',
                '/register',
                $userCredentials
            );
        } catch (FailureResponseException $e) {
            $error = $e->getError();

            if ('ERR_VALIDATION' === $error->getError()) {
                throw new ValidationException($error->getDetails());
            }

            throw $e;
        }

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
     *
     * @return User
     *
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

        try {
            $loggedInClientResponse = $this->billingClient->billingRequest(
                'POST',
                '/auth',
                $userCredentials
            );
        } catch (FailureResponseException $e) {
            $error = $e->getError();

            if (401 === $error->getCode()) {
                throw new AuthenticationException($error->getMessage());
            }

            throw $e;
        }

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
     *
     * @return User
     *
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function updateJwt(User $user)
    {
        $userOldData = $this->serializer->serialize($user, 'json');

        try {
            $updatedUserResponse = $this->billingClient->billingRequest(
                'POST',
                '/token/refresh',
                $userOldData,
                $user->getApiToken()
            );
        } catch (FailureResponseException $e) {
            $error = $e->getError();

            if (401 === $error->getCode()) {
                throw new AuthenticationException($e->getMessage());
            }

            throw $e;
        }

        /** @var AuthenticationDataDto $authenticationData */
        $refreshedData = $this->serializer->deserialize(
            $updatedUserResponse,
            AuthenticationDataDto::class,
            'json'
        );

        $user->updateTokensWithDto($refreshedData);
    }
}
