<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Priority;
use App\Entity\Status;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminConfigController extends AbstractController
{
    #[Route('/admin/config', name: 'app_admin_config')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_config', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $action = $request->request->get('action');
            if ($action === 'add_category') {
                $label = trim((string) $request->request->get('label'));
                if ($label === '') {
                    $this->addFlash('warning', 'Category label is required.');
                } elseif ($entityManager->getRepository(Category::class)->findOneBy(['label' => $label])) {
                    $this->addFlash('warning', 'Category already exists.');
                } else {
                    $category = new Category();
                    $category->setLabel($label);
                    $entityManager->persist($category);
                    $entityManager->flush();
                    $this->addFlash('success', 'Category added.');
                }
            } elseif ($action === 'add_priority') {
                $label = trim((string) $request->request->get('label'));
                $level = (int) $request->request->get('level');
                if ($label === '' || $level <= 0) {
                    $this->addFlash('warning', 'Priority label and level are required.');
                } elseif ($entityManager->getRepository(Priority::class)->findOneBy(['label' => $label])) {
                    $this->addFlash('warning', 'Priority already exists.');
                } else {
                    $priority = new Priority();
                    $priority->setLabel($label);
                    $priority->setLevel($level);
                    $entityManager->persist($priority);
                    $entityManager->flush();
                    $this->addFlash('success', 'Priority added.');
                }
            } elseif ($action === 'add_status') {
                $label = trim((string) $request->request->get('label'));
                if ($label === '') {
                    $this->addFlash('warning', 'Status label is required.');
                } elseif ($entityManager->getRepository(Status::class)->findOneBy(['label' => $label])) {
                    $this->addFlash('warning', 'Status already exists.');
                } else {
                    $status = new Status();
                    $status->setLabel($label);
                    $entityManager->persist($status);
                    $entityManager->flush();
                    $this->addFlash('success', 'Status added.');
                }
            }

            return $this->redirectToRoute('app_admin_config');
        }

        $view = $request->query->get('view');
        $categoryRepo = $entityManager->getRepository(Category::class);
        
        $categoriesData = ($view === 'categories') 
            ? $categoryRepo->findAllWithTicketCount() // Returns array of ['category' => Entity, 'count' => int]
            : $categoryRepo->findAll(); // Returns array of Entities

        return $this->render('admin/config.html.twig', [
            'categories_data' => $categoriesData, // Renamed variable to avoid confusion
            'priorities' => $entityManager->getRepository(Priority::class)->findAll(),
            'statuses' => $entityManager->getRepository(Status::class)->findAll(),
            'view' => $view,
            'page_title' => $view === 'categories' ? 'Active Ticket Categories' : null,
        ]);
    }

    #[Route('/admin/config/category/{id}/delete', name: 'app_admin_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCategory(Category $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_category_' . $category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $entityManager->remove($category);
            $entityManager->flush();
            $this->addFlash('success', 'Category deleted.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'Category is in use and cannot be deleted.');
        }

        return $this->redirectToRoute('app_admin_config');
    }

    #[Route('/admin/config/priority/{id}/delete', name: 'app_admin_priority_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePriority(Priority $priority, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_priority_' . $priority->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $entityManager->remove($priority);
            $entityManager->flush();
            $this->addFlash('success', 'Priority deleted.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'Priority is in use and cannot be deleted.');
        }

        return $this->redirectToRoute('app_admin_config');
    }

    #[Route('/admin/config/status/{id}/delete', name: 'app_admin_status_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteStatus(Status $status, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_status_' . $status->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $entityManager->remove($status);
            $entityManager->flush();
            $this->addFlash('success', 'Status deleted.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'Status is in use and cannot be deleted.');
        }

        return $this->redirectToRoute('app_admin_config');
    }
}
