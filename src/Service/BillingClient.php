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
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
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

        $data = $this->billingRequest('POST', '/register', $userCredentials, null);

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize($data, AuthenticationDataDto::class, 'json');
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

        $data = $this->billingRequest('POST', '/auth', $userCredentials, null);

        /** @var AuthenticationDataDto $authenticationData */
        $authenticationData = $this->serializer->deserialize($data, AuthenticationDataDto::class, 'json');
        return User::createFromDto($authenticationData);
    }

    /**
     * @return BillingUserDto
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function currentClient(): BillingUserDto
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        $data = $this->billingRequest('GET', '/users/current', null, $user->getApiToken());

        /** @var BillingUserDto $billingClient */
        $billingClient = $this->serializer->deserialize($data, BillingUserDto::class, 'json');

        return $billingClient;
    }

    /**
     * @param string $method
     * @param string $urlSuffix
     * @param null $body
     * @param null $authorizationToken
     * @return string
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    private function billingRequest(string $method, string $urlSuffix, $body = null, $authorizationToken = null)
    {
        $requestParams = ['headers' => ['Content-Type' => 'application/json']];
        if ($authorizationToken) {
            $requestParams['headers']['Authorization'] =  'Bearer '. $authorizationToken;
        }
        if ($body) {
            $requestParams['body'] = $body;
        }
        try {
            $response = $this->httpClient->request(
                $method,
                "http://$this->billingUrlBase/api/$this->billingApiVersion$urlSuffix",
                $requestParams
            );

            $responseStatus = $response->getStatusCode();
            $data = $response->getContent(false);

        } catch (TransportExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        } catch (ClientExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        } catch (RedirectionExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        } catch (ServerExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        }

        if (!in_array($responseStatus, [Response::HTTP_OK, Response::HTTP_CREATED], true)) {

            if ($responseStatus === Response::HTTP_UNAUTHORIZED) {

                /** @var AuthenticationErrorDto $authenticationError */
                $authenticationError = $this->serializer->deserialize($data, AuthenticationErrorDto::class, 'json');

                throw new AuthenticationException($authenticationError);
            }

            /** @var FailureResponseDto $error */
            $error = $this->serializer->deserialize($data, FailureResponseDto::class, 'json');

            throw new FailureResponseException($error);
        }

        return $data;
    }
}
