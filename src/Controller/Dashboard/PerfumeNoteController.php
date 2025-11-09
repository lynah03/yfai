<?php

namespace App\Controller\Dashboard;

use App\Entity\PerfumeNote;
use App\Form\Dashboard\PerfumeNoteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/perfume_note', name: 'dashboard_perfume_note_')]final class PerfumeNoteController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $perfumeNotes = $entityManager
            ->getRepository(PerfumeNote::class)
            ->findAll();

        return $this->render('dashboard/perfume_note/index.html.twig', [
            'perfume_notes' => $perfumeNotes,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $perfumeNote = new PerfumeNote();
        $form = $this->createForm(PerfumeNoteType::class, $perfumeNote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($perfumeNote);
            $entityManager->flush();

            return $this->redirectToRoute('dashboard_perfume_note_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/perfume_note/new.html.twig', [
            'perfume_note' => $perfumeNote,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(PerfumeNote $perfumeNote): Response
    {
        return $this->render('dashboard/perfume_note/show.html.twig', [
            'perfume_note' => $perfumeNote,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PerfumeNote $perfumeNote, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PerfumeNoteType::class, $perfumeNote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('dashboard_perfume_note_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/perfume_note/edit.html.twig', [
            'perfume_note' => $perfumeNote,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PerfumeNote $perfumeNote, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$perfumeNote->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($perfumeNote);
            $entityManager->flush();
        }

        return $this->redirectToRoute('dashboard_perfume_note_index', [], Response::HTTP_SEE_OTHER);
    }
}
