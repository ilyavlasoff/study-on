<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\CourseType;
use App\Model\CourseListItemDto;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\CoursesQueryClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
     * @Route("/", name="course_index", methods={"GET"})
     */
    public function index(CourseRepository $courseRepository, CoursesQueryClient $coursesQueryClient): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            /** @var CourseListItemDto[] $billingAvailableCourses */
            $billingCourses = $coursesQueryClient->getAuthorizedCoursesList($user);
        } else {
            $billingCourses = $coursesQueryClient->getCoursesList();
        }

        /** @var Course[] $contentAvailableCourses */
        $availableCourses = $courseRepository->findAll();

        $orderedBillingCourses = [];
        foreach ($availableCourses as $course) {
            $arrayFiltered = array_filter($billingCourses, static function (CourseListItemDto $item) use ($course) {
                return $item->getCode() === $course->getCode();
            });

            if (count($arrayFiltered)) {
                $orderedBillingCourses[] = array_values($arrayFiltered)[0];
            }
        }

        return $this->render('course/index.html.twig', [
            'entityCourses' => $availableCourses,
            'billingCourses' => $orderedBillingCourses,
        ]);
    }

    /**
     * @Route("/new", name="course_new", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_show", methods={"GET"})
     */
    public function show(Course $course, CoursesQueryClient $coursesQueryClient): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $billingCourse = $coursesQueryClient->getCourseByCode($course->getCode());
            $lessons = $this->getDoctrine()->getRepository(Lesson::class)->findBy(
                ['course' => $course->getId()],
                ['indexNumber' => 'ASC']
            );
        } else {

        }


        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $lessons,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="course_edit", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Course $course): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_delete", methods={"DELETE"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Course $course): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('course_index');
    }
}
