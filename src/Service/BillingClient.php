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
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
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
        SerializerInterface $serializer
    ) {
        $this->httpClient = $httpClient;
        $this->billingUrlBase = $billingUrlBase;
        $this->billingApiVersion = $billingApiVersion;
        $this->serializer = $serializer;
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
    public function billingRequest(string $method, string $urlSuffix, $body = null, $authorizationToken = null)
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
        } catch (HttpExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        }

        if (!in_array($responseStatus, [Response::HTTP_OK, Response::HTTP_CREATED], true)) {
            if ($responseStatus === Response::HTTP_UNAUTHORIZED) {

                /** @var AuthenticationErrorDto $authenticationError */
                $authenticationError = $this->serializer->deserialize(
                    $data,
                    AuthenticationErrorDto::class,
                    'json'
                );

                throw new AuthenticationException($authenticationError);
            }

            /** @var FailureResponseDto $error */
            $error = $this->serializer->deserialize($data, FailureResponseDto::class, 'json');

            throw new FailureResponseException($error);
        }

        return $data;
    }
}
