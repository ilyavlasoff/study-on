<?php

namespace App\Service;

use App\Entity\Course;
use App\Security\User;
use JMS\Serializer\SerializerInterface;

class PaymentQueryClient
{
    private $billingClient;

    public function __construct(BillingClient $billingClient, SerializerInterface $serializer)
    {
        $this->billingClient = $billingClient;
    }

    public function buyCourse(Course $course, User $user)
    {
        $this->billingClient->billingRequest(
            'POST',
            "/courses/{$course->getCode()}/pay",
            [],
            $user->getApiToken()
        );
    }
}
