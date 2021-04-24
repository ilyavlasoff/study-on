<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\FailureResponseException;
use App\Service\CoursesQueryClient;
use App\Service\PaymentQueryClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PaymentController
 *
 * @Route("/payments")
 */
class PaymentController extends AbstractController
{
    /**
     * @Route("/pay/{id}", name="pay_for_course", methods={"POST", "GET"})
     */
    public function payCourse(
        Course $course,
        Request $request,
        PaymentQueryClient $paymentQueryClient,
        CoursesQueryClient $coursesQueryClient
    ) {
        /** @var \App\Security\User $user */
        $user = $this->getUser();

        $billingCourse = $coursesQueryClient->getCourseByCode($course, $user);

        if ($billingCourse->getOwned()) {
            return new RedirectResponse($this->generateUrl('course_show', ['id' => $course->getId()]));
        }

        $paymentProceedForm = $this->createFormBuilder()
            ->add('save', SubmitType::class)
            ->getForm();

        $paymentProceedForm->handleRequest($request);
        if ($paymentProceedForm->isSubmitted() && $paymentProceedForm->isValid()) {
            try {
                $paymentQueryClient->buyCourse($course, $user);
            } catch (FailureResponseException $e) {
                $error = $e->getError();

                if (406 === $error->getCode()) {
                    $this->addFlash('error', $error->getMessage());
                }
            }

            return new RedirectResponse($this->generateUrl('course_index'));
        }

        $courseInformation = [];
        $courseInformation['name'] = $course->getName();
        $courseInformation['type'] = $billingCourse->getType();
        $courseInformation['price'] = $billingCourse->getPrice();

        return $this->render('payment/payment.html.twig', [
            'totalSum' => $billingCourse->getPrice(),
            'paidCourses' => [$courseInformation],
            'proceedPayment' => $paymentProceedForm->createView(),
        ]);
    }
}
