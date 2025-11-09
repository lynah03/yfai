<?php

namespace App\Controller\Dashboard;

use App\Entity\UserProfile;
use App\Form\Dashboard\UserProfileType;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/user_profile', name: 'dashboard_user_profile_')]
final class UserProfileController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(UserProfileRepository $userProfileRepository): Response
    {
        return $this->render('dashboard/user_profile/index.html.twig', [
            'user_profiles' => $userProfileRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $userProfile = new UserProfile();
        $form = $this->createForm(UserProfileType::class, $userProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($userProfile);
            $entityManager->flush();

            return $this->redirectToRoute('dashboard_user_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/user_profile/new.html.twig', [
            'user_profile' => $userProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(UserProfile $userProfile): Response
    {
        return $this->render('dashboard/user_profile/show.html.twig', [
            'user_profile' => $userProfile,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserProfile $userProfile, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserProfileType::class, $userProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('dashboard_user_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/user_profile/edit.html.twig', [
            'user_profile' => $userProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, UserProfile $userProfile, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$userProfile->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($userProfile);
            $entityManager->flush();
        }

        return $this->redirectToRoute('dashboard_user_profile_index', [], Response::HTTP_SEE_OTHER);
    }
}
