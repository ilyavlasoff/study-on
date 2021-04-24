<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\ValidationException;
use App\Form\CourseType;
use App\Model\Mapping\CourseTypeMapping;
use App\Model\Response\CourseDto;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\CoursesQueryClient;
use App\Service\PersonalQueryClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        /** @var User|null $user */
        $user = $this->getUser();

        /** @var CourseDto $billingCourses */
        $billingCourses = $coursesQueryClient->getCoursesList($user);

        /** @var Course[] $contentAvailableCourses */
        $coursesContent = $courseRepository->findAll();

        $orderedBillingCourses = [];
        foreach ($billingCourses as $course) {
            $contentFiltered = array_values(array_filter($coursesContent, static function (Course $item) use ($course) {
                return $item->getCode() === $course->getCode();
            }));

            if (count($contentFiltered)) {
                $orderedBillingCourses[$course->getCode()] = [
                    'billing' => $course,
                    'content' => $contentFiltered[0],
                ];
            }
        }

        return $this->render('course/index.html.twig', [
            'coursesData' => $orderedBillingCourses,
        ]);
    }

    /**
     * @Route("/new", name="course_new", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request, CoursesQueryClient $coursesQueryClient, ValidatorInterface $validator): Response
    {
        /** @var \App\Security\User $user */
        $user = $this->getUser();
        $courseTypeMapper = new CourseTypeMapping();
        $form = $this->createForm(CourseType::class, $courseTypeMapper);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $course = new Course();
                $courseContent = $courseTypeMapper->updateCourseContent($course);
                $entityManager = $this->getDoctrine()->getManager();
                $errors = $validator->validate($courseContent);
                if (count($errors) > 0) {
                    $errorsMsg = [];
                    foreach ($errors as $error) {
                        $errorsMsg[$error->getPropertyPath()] = $error->getMessage();
                    }
                    throw new ValidationException($errorsMsg);
                }

                $courseDto = $courseTypeMapper->getBillingCourse();
                $created = $coursesQueryClient->createCourse($courseDto, $user);

                if (!$created) {
                    throw new \Exception('Can not create course');
                }

                $entityManager->persist($courseContent);
                $entityManager->flush();

                return $this->redirectToRoute('course_show', ['id' => $courseContent->getId()]);
            } catch (ValidationException $e) {
                foreach ($e->getDetails() as $errorPath => $errorMessage) {
                    if ($form->has($errorPath)) {
                        $form->get($errorPath)->addError(new FormError($errorMessage));
                    }
                }
            }
        }

        return $this->render('course/new.html.twig', [
            'mapper' => $courseTypeMapper,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_show", methods={"GET"})
     */
    public function show(Course $course, CoursesQueryClient $coursesQueryClient, PersonalQueryClient $personalQueryClient): Response
    {
        $allowToViewCourse = false;
        $allowToBuyCourse = false;

        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            /** @var User $user */
            $user = $this->getUser();

            $billingCourse = $coursesQueryClient->getCourseByCode($course, $user);

            if ($billingCourse->getOwned()) {
                $allowToViewCourse = true;
            } else {
                $billingUser = $personalQueryClient->currentClient($user);

                if ($billingUser->getBalance() >= $billingCourse->getPrice()) {
                    $allowToBuyCourse = true;
                }
            }
        } else {
            $billingCourse = $coursesQueryClient->getCourseByCode($course);
        }

        if ($allowToViewCourse) {
            /** @var \App\Repository\LessonRepository $lessonRepo */
            $lessonRepository = $this->getDoctrine()->getRepository(Lesson::class);

            $courseLessons = $lessonRepository->findBy(
                ['course' => $course->getId()],
                ['indexNumber' => 'ASC']
            );

            return $this->render('course/show.html.twig', [
                'course' => $course,
                'billing' => $billingCourse,
                'lessons' => $courseLessons,
            ]);
        } else {
            return $this->render('course/show_offer.html.twig', [
                'course' => $course,
                'billing' => $billingCourse,
                'allowToBuy' => $allowToBuyCourse,
            ]);
        }
    }

    /**
     * @Route("/{id}/edit", name="course_edit", methods={"GET","POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(
        Request $request,
        Course $course,
        CoursesQueryClient $coursesQueryClient,
        ValidatorInterface $validator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $courseDto = $coursesQueryClient->getCourseByCode($course, $user);

        $courseTypeMapper = CourseTypeMapping::fromExistingCourseData($courseDto, $course);

        $form = $this->createForm(CourseType::class, $courseTypeMapper);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $previousCourse = clone $course;

                $courseTypeMapper->updateCourseContent($previousCourse);

                $errors = [];
                $errors[] = $validator->validateProperty($previousCourse, 'code');
                $errors[] = $validator->validateProperty($previousCourse, 'name');
                $errors[] = $validator->validateProperty($previousCourse, 'description');

                $errorsMsg = [];
                foreach ($errors as $errorDetails) {
                    foreach ($errorDetails as $errorDetail) {
                        $errorsMsg[$errorDetail->getPropertyPath()] = $errorDetail->getMessage();
                    }
                }
                if(count($errorsMsg) > 0) {
                    throw new ValidationException($errorsMsg);
                }

                $courseDto = $courseTypeMapper->getBillingCourse();

                $isEdited = $coursesQueryClient->editCourse($course, $courseDto, $user);
                if (!$isEdited) {
                    throw new \Exception('Error occurred');
                }

                $courseTypeMapper->updateCourseContent($course);
                $this->getDoctrine()->getManager()->flush();

                return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
            } catch (ValidationException $e) {
                foreach ($e->getDetails() as $errorPath => $errorMessage) {
                    if ($form->has($errorPath)) {
                        $form->get($errorPath)->addError(new FormError($errorMessage));
                    }
                }
            }
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'mapper' => $courseTypeMapper,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="course_delete", methods={"DELETE"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Course $course, CoursesQueryClient $coursesQueryClient): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();

            $coursesQueryClient->dropCourse($course, $this->getUser());

            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('course_index');
    }
}
