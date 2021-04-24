<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\BillingUnavailableException;
use App\Model\Response\BillingUserDto;
use App\Model\Response\TransactionHistoryDto;
use App\Security\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;

class PersonalQueryClient
{
    private $billingClient;
    private $serializer;
    private $entityManager;

    public function __construct(
        BillingClient $billingClient,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ) {
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
    }

    /**
     * @return BillingUserDto
     *
     * @throws BillingUnavailableException
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
            'array<App\Model\Response\TransactionHistoryDto>',
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
