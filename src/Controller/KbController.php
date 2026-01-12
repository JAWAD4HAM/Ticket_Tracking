<?php

namespace App\Controller;

use App\Entity\KbArticle;
use App\Entity\Category;
use App\Repository\KbArticleRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kb')]
class KbController extends AbstractController
{
    #[Route('/', name: 'app_kb_index', methods: ['GET'])]
    public function index(KbArticleRepository $kbArticleRepository, CategoryRepository $categoryRepository, Request $request): Response
    {
        $keyword = $request->query->get('q');
        $categoryId = $request->query->get('category');
        $categoryId = $categoryId ? (int) $categoryId : null;

        $articles = $kbArticleRepository->searchByKeywordAndCategory($keyword, $categoryId);
        $categories = $categoryRepository->findAll();

        return $this->render('kb/index.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'currentCategory' => $categoryId,
            'searchTerm' => $keyword
        ]);
    }

    #[Route('/manage', name: 'app_kb_manage', methods: ['GET'])]
    #[IsGranted('ROLE_TECH')]
    public function manage(KbArticleRepository $kbArticleRepository): Response
    {
        return $this->render('kb/manage.html.twig', [
            'articles' => $kbArticleRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_kb_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_TECH')]
    public function new(Request $request, EntityManagerInterface $entityManager, CategoryRepository $categoryRepository): Response
    {
        if ($request->isMethod('POST')) {
            $article = new KbArticle();
            $article->setTitle($request->request->get('title'));
            $article->setContent($request->request->get('content'));
            $article->setIsPublished($request->request->has('isPublished'));
            $article->setAuthor($this->getUser());
            
            $categoryId = $request->request->get('category');
            if ($categoryId) {
                $category = $categoryRepository->find($categoryId);
                $article->setCategory($category);
            }

            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('app_kb_manage', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('kb/form.html.twig', [
            'page_title' => 'Create Article',
            'article' => null,
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_kb_show', methods: ['GET'])]
    public function show(KbArticle $kbArticle): Response
    {
        if (!$kbArticle->isPublished() && !$this->isGranted('ROLE_TECH')) {
            throw $this->createAccessDeniedException('This article is not published.');
        }

        return $this->render('kb/show.html.twig', [
            'article' => $kbArticle,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_kb_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_TECH')]
    public function edit(Request $request, KbArticle $kbArticle, EntityManagerInterface $entityManager, CategoryRepository $categoryRepository): Response
    {
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_MANAGER') && $kbArticle->getAuthor() !== $user) {
            throw $this->createAccessDeniedException('You can only edit your own articles.');
        }

        if ($request->isMethod('POST')) {
            $kbArticle->setTitle($request->request->get('title'));
            $kbArticle->setContent($request->request->get('content'));
            $kbArticle->setIsPublished($request->request->has('isPublished'));
            
             $categoryId = $request->request->get('category');
            if ($categoryId) {
                $category = $categoryRepository->find($categoryId);
                $kbArticle->setCategory($category);
            } else {
                $kbArticle->setCategory(null);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_kb_manage', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('kb/form.html.twig', [
            'page_title' => 'Edit Article',
            'article' => $kbArticle,
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_kb_delete', methods: ['POST'])]
    #[IsGranted('ROLE_TECH')]
    public function delete(Request $request, KbArticle $kbArticle, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_MANAGER') && $kbArticle->getAuthor() !== $user) {
            throw $this->createAccessDeniedException('You can only delete your own articles.');
        }

        if ($this->isCsrfTokenValid('delete'.$kbArticle->getId(), $request->request->get('_token'))) {
            $entityManager->remove($kbArticle);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_kb_manage', [], Response::HTTP_SEE_OTHER);
    }
}
