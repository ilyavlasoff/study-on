<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Model\Response\ErrorResponseDto;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClient
{
    private $httpClient;
    private $billingUrlBase;
    private $billingApiVersion;
    private $serializer;

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
     *
     * @return string
     *
     * @throws BillingUnavailableException
     */
    public function billingRequest(string $method, string $urlSuffix, $body = null, $authorizationToken = null)
    {
        $requestParams = ['headers' => ['Content-Type' => 'application/json']];
        if ($authorizationToken) {
            $requestParams['headers']['Authorization'] = 'Bearer ' . $authorizationToken;
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
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            throw new BillingUnavailableException('Service is unavailable');
        }

        if (!in_array($responseStatus, [Response::HTTP_OK, Response::HTTP_CREATED], true)) {
            /** @var ErrorResponseDto $authenticationError */
            $error = $this->serializer->deserialize(
                $data,
                ErrorResponseDto::class,
                'json'
            );

            throw new FailureResponseException($error);
        }

        return $data;
    }
}
