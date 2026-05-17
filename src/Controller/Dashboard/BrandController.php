<?php

namespace App\Controller\Dashboard;

use App\Entity\Brand;
use App\Form\Dashboard\BrandType;
use App\Repository\BrandRepository;
use App\Service\DocumentUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/brand', name: 'dashboard_brand_')]
final class BrandController extends AbstractController
{
    public function __construct(
        private readonly DocumentUploadService $documentUploader,
    ) {
    }

    #[Route(name: 'index', methods: ['GET'])]
    public function index(BrandRepository $brandRepository): Response
    {
        return $this->render('dashboard/brand/index.html.twig', [
            'brands' => $brandRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $brand = new Brand();
        $form = $this->createForm(BrandType::class, $brand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleLogoUpload($form, $brand);

            $entityManager->persist($brand);
            $entityManager->flush();

            $this->addFlash('success', 'Brand created successfully.');

            return $this->redirectToRoute('dashboard_brand_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/brand/new.html.twig', [
            'brand' => $brand,
            'form' => $form,
        ]);
    }

    #[Route('/show/{id}', name: 'show', methods: ['GET'])]
    public function show(Brand $brand): Response
    {
        return $this->render('dashboard/brand/show.html.twig', [
            'brand' => $brand,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Brand $brand, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BrandType::class, $brand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleLogoUpload($form, $brand);

            $entityManager->flush();

            $this->addFlash('success', 'Brand updated successfully.');

            return $this->redirectToRoute('dashboard_brand_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/brand/edit.html.twig', [
            'brand' => $brand,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Brand $brand, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$brand->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($brand);
            $entityManager->flush();

            $this->addFlash('success', 'Brand deleted successfully.');
        }

        return $this->redirectToRoute('dashboard_brand_index', [], Response::HTTP_SEE_OTHER);
    }

    private function handleLogoUpload($form, Brand $brand): void
    {
        if (!$form->has('logo')) {
            return;
        }

        $logoFile = $form->get('logo')->getData();

        if (!$logoFile) {
            return;
        }

        $uploaded = $this->documentUploader->uploadDocument(
            $logoFile,
            $this->getParameter('brand_directory')
        );

        $brand->setLogo($uploaded);
    }
}
