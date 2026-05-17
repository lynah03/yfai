<?php

namespace App\Controller\Dashboard;

use App\Entity\Perfume;
use App\Form\Dashboard\PerfumeType;
use App\Repository\PerfumeRepository;
use App\Service\DocumentUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/perfume', name: 'dashboard_perfume_')]
final class PerfumeController extends AbstractController
{
    public function __construct(
        private readonly DocumentUploadService $documentUploader,
    ) {
    }

    #[Route(name: 'index', methods: ['GET'])]
    public function index(PerfumeRepository $perfumeRepository): Response
    {
        return $this->render('dashboard/perfume/index.html.twig', [
            'perfumes' => $perfumeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $perfume = new Perfume();
        $form = $this->createForm(PerfumeType::class, $perfume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $perfume);

            $entityManager->persist($perfume);
            $entityManager->flush();

            $this->addFlash('success', 'Perfume created successfully.');

            return $this->redirectToRoute('dashboard_perfume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/perfume/new.html.twig', [
            'perfume' => $perfume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Perfume $perfume): Response
    {
        return $this->render('dashboard/perfume/show.html.twig', [
            'perfume' => $perfume,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Perfume $perfume, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PerfumeType::class, $perfume);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $perfume);

            $entityManager->flush();

            $this->addFlash('success', 'Perfume updated successfully.');

            return $this->redirectToRoute('dashboard_perfume_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/perfume/edit.html.twig', [
            'perfume' => $perfume,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Perfume $perfume, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$perfume->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($perfume);
            $entityManager->flush();

            $this->addFlash('success', 'Perfume deleted successfully.');
        }

        return $this->redirectToRoute('dashboard_perfume_index', [], Response::HTTP_SEE_OTHER);
    }

    private function handleImageUpload($form, Perfume $perfume): void
    {
        if (!$form->has('imageFile')) {
            return;
        }

        $imageFile = $form->get('imageFile')->getData();

        if (!$imageFile) {
            return;
        }

        $uploaded = $this->documentUploader->uploadDocument(
            $imageFile,
            $this->getParameter('perfume_directory')
        );

        $perfume->setImage($uploaded);
    }
}