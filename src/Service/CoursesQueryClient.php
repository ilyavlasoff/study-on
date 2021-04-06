<?php

namespace App\Service;

use App\Entity\Course;
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

    public function getCoursesList(): array
    {
        try {
            $courses = $this->billingClient->billingRequest(
                'GET',
                '/courses'
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->serializer->deserialize(
            $courses,
            'array<App\Model\CourseListItemDto>',
            'json'
        );

    }

    public function getAuthorizedCoursesList(User $user): array
    {
        try {
            $courses = $this->billingClient->billingRequest(
                'GET',
                '/labeled-courses',
                [],
                $user->getApiToken()
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->serializer->deserialize(
            $courses,
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

    public function getCourseByCode(Course $course)
    {
        try {
            $receivedCourse = $this->billingClient->billingRequest(
                'GET',
                "/courses/{$course->getCode()}",
                [],
                null
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
