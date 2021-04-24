<?php

namespace App\Tests\Mocks;

use App\Entity\Course;
use App\Model\Response\BillingUserDto;
use App\Model\Response\TransactionHistoryDto;
use App\Security\User;
use App\Service\PersonalQueryClient;
use Doctrine\ORM\EntityManagerInterface;

class PersonalQueryClientMock extends PersonalQueryClient
{
    private $dataMock;
    private $entityManager;

    public function __construct(DataMock $dataMock, EntityManagerInterface $entityManager)
    {
        $this->dataMock = $dataMock;
        $this->entityManager = $entityManager;
    }

    public function currentClient(User $user): BillingUserDto
    {
        $this->dataMock->testUserValid($user);
        $loadedUser = $this->dataMock->registeredUsers[$user->getUsername()];

        $billingUser = new BillingUserDto();
        $billingUser->setUsername($loadedUser->getUsername());
        $billingUser->setRoles($loadedUser->getRoles());
        $billingUser->setBalance($this->dataMock->userBalance[$billingUser->getUsername()]);

        return $billingUser;
    }

    public function getClientTransactions(User $user): array
    {
        $this->dataMock->testUserValid($user);

        if (array_key_exists($user->getUsername(), $this->dataMock->clientTransactions)) {
            /** @var TransactionHistoryDto[] $loadedTransactions */
            $loadedTransactions = $this->dataMock->clientTransactions[$user->getUsername()];

            foreach (array_keys($loadedTransactions) as $transaction) {
                $courseCode = $loadedTransactions[$transaction]->getCourseCode();
                $paidCourse = $this->entityManager->getRepository(Course::class)->findOneBy(['code' => $courseCode]);
                $loadedTransactions[$transaction]->setLocalCourse($paidCourse);
            }

            return $loadedTransactions;
        }

        return [];
    }
}
