<?php

namespace App\Service;

use App\Security\User;
use JMS\Serializer\SerializerInterface;

class CoursesQueryClient
{
    private $billingClient;
    private $serializer;

    public function __construct(
        BillingClient $billingClient,
        SerializerInterface $serializer
    ) {
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;
    }

    public function getAvailableCoursesList(): array
    {
        try {
            $coursesResponse = $this->billingClient->billingRequest(
                'GET',
                '/courses',
                null
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->serializer->deserialize(
            $coursesResponse,
            'array<App\Model\CourseListItemDto>',
            'json'
        );

    }

    public function getBoughtCourses(User $user): array
    {
        try {
            $boughtCourses = $this->billingClient->billingRequest(
                'GET',
                '/my-courses',
                [],
                $user->getApiToken()
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->serializer->deserialize(
            $boughtCourses,
            'array<App\Model\CourseListItemDto>',
            'json'
        );
    }
}
