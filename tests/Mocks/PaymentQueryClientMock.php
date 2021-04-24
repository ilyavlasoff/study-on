<?php

namespace App\Tests\Mocks;

use App\Entity\Course;
use App\Exception\FailureResponseException;
use App\Model\Response\ErrorResponseDto;
use App\Model\Response\TransactionHistoryDto;
use App\Security\User;
use App\Service\PaymentQueryClient;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentQueryClientMock extends PaymentQueryClient
{
    private $dataMock;

    public function __construct(DataMock $dataMock)
    {
        $this->dataMock = $dataMock;
    }

    public function buyCourse(Course $course, User $user)
    {
        $this->dataMock->testUserValid($user);

        if (!array_key_exists($course->getCode(), $this->dataMock->courses)) {
            throw new NotFoundHttpException();
        }

        $billingCourse = $this->dataMock->courses[$course->getCode()];
        if ('free' !== $billingCourse->getType()) {
            if ($this->dataMock->userBalance[$user->getUsername()] < $billingCourse->getPrice()) {
                $error = new ErrorResponseDto();
                $error->setCode(406);
                throw new FailureResponseException($error);
            }

            $this->dataMock->userBalance[$user->getUsername()] -= $billingCourse->getPrice();
        }

        $transaction = new TransactionHistoryDto();
        $transaction->setCreatedAt(new \DateTime());
        $transaction->setCourseCode($billingCourse->getCode());
        $transaction->setAmount($billingCourse->getPrice() ?? 0);
        $transaction->setType('payment');

        $this->dataMock->boughtCoursesByClient[$user->getUsername()][] = $billingCourse;
    }
}
