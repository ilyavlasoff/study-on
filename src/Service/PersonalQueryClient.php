<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\AuthenticationException;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Model\BillingUserDto;
use App\Model\TransactionHistoryDto;
use App\Security\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PersonalQueryClient
{
    private $billingClient;
    private $serializer;
    private $tokenStorage;
    private $entityManager;

    public function __construct(
        BillingClient $billingClient,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager
    ) {
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
    }

    /**
     * @return BillingUserDto
     * @throws AuthenticationException
     * @throws BillingUnavailableException
     * @throws FailureResponseException
     */
    public function currentClient(User $user): BillingUserDto
    {
        $currentClientResponse = $this->billingClient->billingRequest(
            'GET',
            '/users/current',
            null,
            $user->getApiToken()
        );

        /** @var BillingUserDto $billingClient */
        $billingClient = $this->serializer->deserialize(
            $currentClientResponse,
            BillingUserDto::class,
            'json'
        );

        return $billingClient;
    }

    public function getClientTransactions(User $user): array
    {
        $clientTransactionsResponse = $this->billingClient->billingRequest(
            'GET',
            '/transactions',
            null,
            $user->getApiToken()
        );

        /** @var TransactionHistoryDto[] $clientTransactions */
        $clientTransactions = $this->serializer->deserialize(
            $clientTransactionsResponse,
            'array<App\Model\TransactionHistoryDto>',
            'json'
        );

        foreach ($clientTransactions as $clientTransaction) {
            if ($paidCourseCode = $clientTransaction->getCourseCode()) {
                $paidCourse = $this->entityManager->getRepository(Course::class)->findOneBy(['code' => $paidCourseCode]);
                $clientTransaction->setLocalCourse($paidCourse);
            }
        }

        return $clientTransactions;
    }
}
