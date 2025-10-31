<?php

namespace App\Controller\Dashboard;

use App\Entity\Perfume;
use App\Form\PerfumeType;
use App\Repository\PerfumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/perfume')]
final class PerfumeController extends AbstractController
{
    #[Route(name: 'app_perfume_index', methods: ['GET'])]
    public function index(PerfumeRepository $perfumeRepository): Response
    {
        return $this->render('perfume/index.html.twig', [
            'perfumes' => $perfumeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_perfume_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $perfume = new Perfume();
        $form = $this->createForm(PerfumeType::class, $perfume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($perfume);
            $entityManager->flush();

            return $this->redirectToRoute('app_perfume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('perfume/new.html.twig', [
            'perfume' => $perfume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_perfume_show', methods: ['GET'])]
    public function show(Perfume $perfume): Response
    {
        return $this->render('perfume/show.html.twig', [
            'perfume' => $perfume,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_perfume_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Perfume $perfume, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PerfumeType::class, $perfume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_perfume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('perfume/edit.html.twig', [
            'perfume' => $perfume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_perfume_delete', methods: ['POST'])]
    public function delete(Request $request, Perfume $perfume, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$perfume->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($perfume);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_perfume_index', [], Response::HTTP_SEE_OTHER);
    }
}
