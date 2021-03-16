<?php

namespace App\Service;

use App\Exception\AuthenticationException;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Model\AuthenticationDataDto;
use App\Model\AuthenticationErrorDto;
use App\Model\BillingUserDto;
use App\Model\FailureResponseDto;
use App\Model\UserRegisterCredentialsDto;
use App\Security\User;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClient
{
    private $httpClient;
    private $billingUrlBase;
    private $billingApiVersion;
    private $serializer;
    private $tokenStorage;

    public function __construct(
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage
    ) {
        $this->httpClient = $httpClient;
        $this->billingUrlBase = $billingUrlBase;
        $this->billingApiVersion = $billingApiVersion;
        $this->serializer = $serializer;
        $this->tokenStorage = $tokenStorage;
    }

    public function register(UserRegisterCredentialsDto $credentials): User
    {
        $userCredentials = $this->serializer->serialize(
            $credentials,
            'json',
            SerializationContext::create()->setGroups(['reg'])
        );

        try {
            $response = $this->httpClient->request(
                'POST',
                "http://$this->billingUrlBase/api/$this->billingApiVersion/register",
                ['body' => $userCredentials]
            );
        } catch (TransportExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        } catch (\Exception $e) {
            throw $e;
        }

        $responseStatus = $response->getStatusCode();
        $data = $response->getContent(false);

        if ($responseStatus !== Response::HTTP_CREATED) {

            /** @var FailureResponseDto $registrationError */
            $registrationError = $this->serializer->deserialize($data, FailureResponseDto::class, 'json');

            throw new FailureResponseException($registrationError);
        }

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize($data, AuthenticationDataDto::class, 'json');
        return User::createFromDto($authenticationData);
    }

    public function login(UserRegisterCredentialsDto $credentials): User
    {
        $userCredentials = $this->serializer->serialize(
            $credentials,
            'json',
            SerializationContext::create()->setGroups(['auth'])
        );

        try {
            $response = $this->httpClient->request(
                'GET',
                "http://$this->billingUrlBase/api/$this->billingApiVersion/auth",
                ['body' => $userCredentials, 'headers' => ['Content-Type' => 'application/json']]
            );
        } catch (TransportExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        } catch (\Exception $e) {
            throw $e;
        }

        $responseStatus = $response->getStatusCode();
        $data = $response->getContent(false);

        if ($responseStatus === Response::HTTP_OK) {

            /** @var AuthenticationDataDto $authenticationData */
            $authenticationData = $this->serializer->deserialize($data, AuthenticationDataDto::class, 'json');

            return User::createFromDto($authenticationData);
        } elseif ($responseStatus === Response::HTTP_UNAUTHORIZED) {

            /** @var AuthenticationErrorDto $authenticationError */
            $authenticationError = $this->serializer->deserialize($data, AuthenticationErrorDto::class, 'json');

            throw new AuthenticationException($authenticationError);
        } else {

            /** @var FailureResponseDto $error */
            $error = $this->serializer->deserialize($data, FailureResponseDto::class, 'json');

            throw new FailureResponseException($error);
        }
    }

    public function currentClient(): BillingUserDto
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();

        try {
            $response = $this->httpClient->request(
                'GET',
                "http://$this->billingUrlBase/api/$this->billingApiVersion/users/current",
                ['headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer '. $user->getApiToken()]]
            );
        } catch (TransportExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        }

        $responseStatus = $response->getStatusCode();
        $data = $response->getContent(false);

        if ($responseStatus !== Response::HTTP_OK) {

            /** @var FailureResponseDto $error */
            $error = $this->serializer->deserialize($data, FailureResponseDto::class, 'json');

            throw new FailureResponseException($error);
        }

        /** @var BillingUserDto $billingClient */
        $billingClient = $this->serializer->deserialize($data, BillingUserDto::class, 'json');

        return $billingClient;
    }
}
