<?php

namespace App\Service;

use App\Entity\Course;
use App\Exception\FailureResponseException;
use App\Exception\ValidationException;
use App\Model\Response\CourseDto;
use App\Security\User;
use JMS\Serializer\DeserializationContext;
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

    public function getCoursesList(User $currentUser = null): array
    {
        if ($currentUser) {
            $urlPath = '/courses';
            $serializeGroup = 'owned';
            $authorizationToken = $currentUser->getApiToken();
        } else {
            $urlPath = '/u/courses';
            $serializeGroup = 'anon';
            $authorizationToken = null;
        }

        try {
            $courses = $this->billingClient->billingRequest(
                'GET',
                $urlPath,
                null,
                $authorizationToken
            );
        } catch (\Exception $e) {
            throw $e;
        }

        $context = new DeserializationContext();
        $context->setGroups($serializeGroup);

        return $this->serializer->deserialize(
            $courses,
            'array<App\Model\Response\CourseDto>',
            'json',
            $context
        );
    }

    public function getCourseByCode(Course $course, User $user = null): CourseDto
    {
        if ($user) {
            $urlPath = "/courses/{$course->getCode()}";
            $serializeGroup = 'owned';
            $authorizationToken = $user->getApiToken();
        } else {
            $urlPath = "/u/courses/{$course->getCode()}";
            $serializeGroup = 'anon';
            $authorizationToken = null;
        }

        try {
            $course = $this->billingClient->billingRequest(
                'GET',
                $urlPath,
                [],
                $authorizationToken
            );

            $context = new DeserializationContext();
            $context->setGroups($serializeGroup);

            return $this->serializer->deserialize(
                $course,
                'App\Model\Response\CourseDto',
                'json',
                $context
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createCourse(CourseDto $course, User $user)
    {
        $serializedCourse = $this->serializer->serialize($course, 'json');

        try {
            $result = $this->billingClient->billingRequest(
                'POST',
                '/courses/',
                $serializedCourse,
                $user->getApiToken()
            );
        } catch (FailureResponseException $e) {
            $error = $e->getError();
            if ('ERR_VALIDATION' === $error->getError()) {
                throw new ValidationException($error->getDetails());
            }
        }

        $decodedResult = json_decode($result, true);

        return array_key_exists('success', $decodedResult) && $decodedResult['success'];
    }

    public function editCourse(Course $original, CourseDto $courseEdited, User $user)
    {
        $serializedCourse = $this->serializer->serialize($courseEdited, 'json');

        try {
            $result = $this->billingClient->billingRequest(
                'POST',
                "/courses/{$original->getCode()}",
                $serializedCourse,
                $user->getApiToken()
            );
        } catch (FailureResponseException $e) {
            $error = $e->getError();
            if ('ERR_VALIDATION' === $error->getError()) {
                throw new ValidationException($error->getDetails());
            }
            throw $e;
        }

        $decodedResult = json_decode($result, true);

        return array_key_exists('success', $decodedResult) && $decodedResult['success'];
    }

    public function dropCourse(Course $course, User $user)
    {
        $result = $this->billingClient->billingRequest(
            'DELETE',
            "/courses/{$course->getCode()}",
            [],
            $user->getApiToken()
        );

        $decodedResult = json_decode($result, true);

        return array_key_exists('success', $decodedResult) && $decodedResult['success'];
    }
}
