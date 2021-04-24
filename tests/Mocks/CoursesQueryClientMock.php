<?php

namespace App\Tests\Mocks;

use App\Entity\Course;
use App\Exception\FailureResponseException;
use App\Exception\ValidationException;
use App\Model\Response\CourseDto;
use App\Model\Response\ErrorResponseDto;
use App\Security\User;
use App\Service\BillingClient;
use App\Service\CoursesQueryClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CoursesQueryClientMock extends CoursesQueryClient
{
    private $dataMock;

    public function __construct(DataMock $dataMock)
    {
        $this->dataMock = $dataMock;
    }

    private function getCoursesErrors(CourseDto $course, $ignoreExist = false): array
    {
        $problems = [];

        if(!$ignoreExist && array_key_exists($course->getCode(), $this->dataMock->courses)) {
            $problems['code'] = 'This course is already exists';
        }

        if ($course->getCode() === '') {
            $problems['code'] = 'Course code can not be blank';
        } elseif(strlen($course->getCode()) > 255) {
            $problems['code'] = 'Maximal code string length is 255 symbols';
        }

        if($course->getType() === '') {
            $problems['type'] = 'Type can not be blank';
        } elseif (!in_array($course->getType(), ['free', 'rent', 'buy'])) {
            $problems['type'] = 'Incorrect course type, available only [free, rent, buy] types';
        }

        if($course->getTitle() === '') {
            $problems['title'] = 'Course title can not be blank';
        } elseif(strlen($course->getTitle()) > 255) {
            $problems['title'] = 'Maximal title length is 255 symbols';
        }

        if($course->getType() === 'rent' && !$course->getRentTime()) {
            $problems['rentTime'] = 'This course must contain rent time';
        }

        if($course->getType() === 'rent' && !$course->getPrice()) {
            $problems['rentTime'] = 'Rent course can not be free';
        }

        if($course->getType() !== 'rent' && $course->getRentTime()) {
            $problems['rentTime'] = 'Non-rent course can not contain rent time value';
        }

        if($course->getType() === 'free' && $course->getPrice()) {
            $problems['type'] = 'Free course can not contain cost value';
        }

        return $problems;
    }

    public function getCoursesList(User $currentUser = null): array
    {
        if($currentUser) {
            $this->dataMock->testUserValid($currentUser);
        }

        $coursesList = $this->dataMock->courses;
        if(!$currentUser) {
            foreach ($coursesList as $key => $value) {
                $coursesList[$key]->setOwned(null);
                $coursesList[$key]->setOwnedUntil(null);
            }
        }

        return $coursesList;
    }

    public function createCourse(CourseDto $course, User $user)
    {
        $this->dataMock->testUserValid($user, 'ROLE_SUPER_ADMIN');

        $errors = $this->getCoursesErrors($course);

        if($errors) {
            throw new ValidationException($errors);
        }

        $this->dataMock->courses[$course->getCode()] = $course;

        return true;
    }

    public function editCourse(Course $original, CourseDto $courseEdited, User $user)
    {
        $this->dataMock->testUserValid($user, 'ROLE_SUPER_ADMIN');

        if(!array_key_exists($original->getCode(), $this->dataMock->courses)) {
            throw new NotFoundHttpException();
        }

        $errors = $this->getCoursesErrors($courseEdited, true);

        if($errors) {
            throw new ValidationException($errors);
        }

        /** @var CourseDto $currentCourse */
        $currentCourse = $this->dataMock->courses[$original->getCode()];
        $courseEdited->setOwned($currentCourse->getOwned());
        $courseEdited->setOwnedUntil($currentCourse->getOwnedUntil());

        if($original->getCode() !== $courseEdited->getCode()) {
            unset($this->dataMock->courses[$original->getCode()]);
        }

        $this->dataMock->courses[$courseEdited->getCode()] = $courseEdited;

        return true;
    }

    public function getCourseByCode(Course $course, User $user = null): CourseDto
    {
        if(!array_key_exists($course->getCode(), $this->dataMock->courses)) {
            throw new NotFoundHttpException();
        }

        $value = $this->dataMock->courses[$course->getCode()];
        if(!$user) {
            $value->setOwned(null);
            $value->setOwnedUntil(null);
        }

        return $value;
    }

    public function dropCourse(Course $course, User $user)
    {
        $this->dataMock->testUserValid($user, 'ROLE_SUPER_ADMIN');

        if(!array_key_exists($course->getCode(), $this->dataMock->courses)) {
            throw new NotFoundHttpException();
        }

        unset($this->dataMock->courses[$course->getCode()]);

        return true;
    }
}