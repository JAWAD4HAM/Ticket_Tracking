<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Priority;
use App\Entity\Status;
use App\Entity\Ticket;
use App\Entity\TicketComment;
use App\Entity\User;
use App\Form\AdminUserEditType;
use App\Form\TicketCommentType;
use App\Form\TicketType;
use App\Form\UserType;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class HelpdeskController extends AbstractController
{
    private const SLA_HOURS_BY_LEVEL = [
        1 => 4,
        2 => 8,
        3 => 24,
        4 => 72,
    ];
    #[Route('/', name: 'app_dashboard')]
    public function dashboard(TicketRepository $ticketRepository): Response
    {
        $user = $this->getUser();
        
        // If not logged in, show generic welcome or prompt login
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_MANAGER')) {
            return $this->render('dashboard/manager.html.twig', [
                'stats' => $ticketRepository->getManagerStats(),
                'recent_tickets' => $ticketRepository->findAllSorted(10),
            ]);
        }

        if ($this->isGranted('ROLE_TECH')) {
            return $this->render('dashboard/tech.html.twig', [
                'assigned_tickets' => $ticketRepository->findAssignedTo($user),
                'unassigned_tickets' => $ticketRepository->findUnassigned(),
            ]);
        }

        // Default to USER
        return $this->render('dashboard/user.html.twig', [
            'tickets' => $ticketRepository->findByCreator($user),
        ]);
    }

    #[Route('/ticket/create', name: 'app_ticket_create')]
    public function createTicket(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ticket = new Ticket();
        $form = $this->createForm(TicketType::class, $ticket);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket->setCreatedAt(new \DateTime());
            
            // Set current logged in user as Creator
            if ($this->getUser()) {
                $ticket->setCreator($this->getUser());
            }

            // Set default status to 'Ouvert' (Open); block creation if missing.
            $status = $entityManager->getRepository(Status::class)->findOneBy(['label' => 'Ouvert']);
            if (!$status) {
                $form->addError(new FormError('Default status "Ouvert" not found. Run app:seed-data.'));
            } else {
                $ticket->setStatus($status);
                $ticket->setSlaDueAt($this->calculateSlaDueAt($ticket->getPriority()));
                $entityManager->persist($ticket);
                $entityManager->flush();

                $this->handleAttachments($ticket, $form->get('attachments')->getData(), $entityManager);

                $this->addFlash('success', 'Ticket created successfully!');

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('create_ticket.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/ticket', name: 'app_ticket')]
    public function ticket(Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        // Only Technicians and Managers should see the "All Tickets" list
        // Clients see their dashboard.
        $this->denyAccessUnlessGranted('ROLE_TECH');

        $statusFilter = $request->query->get('status');
        $priorityFilter = $request->query->get('priority');
        $fromFilter = $request->query->get('from');
        $toFilter = $request->query->get('to');

        $fromDate = $this->parseDateFilter($fromFilter);
        $toDate = $this->parseDateFilter($toFilter, true);

        $tickets = $ticketRepository->findFiltered(
            $statusFilter ?: null,
            $priorityFilter ? (int) $priorityFilter : null,
            $fromDate,
            $toDate
        );

        $statuses = $entityManager->getRepository(Status::class)->findAll();
        $priorities = $entityManager->getRepository(Priority::class)->findAll();

        return $this->render('ticket.html.twig', [
            'tickets' => $tickets,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'filters' => [
                'status' => $statusFilter,
                'priority' => $priorityFilter,
                'from' => $fromFilter,
                'to' => $toFilter,
            ],
        ]);
    }
   
    #[Route('/user/create', name: 'app_user_create')]
    public function createUser(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );
            
            if (!$user->getDbRole()) {
                $user->setDbRole('USER');
            }

            $entityManager->persist($user);
            $entityManager->flush();
            
            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('adduser.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ticket/{id}', name: 'app_ticket_show', requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->assertCanAccessTicket($ticket);

        $commentForm = $this->createForm(TicketCommentType::class, new TicketComment(), [
            'allow_internal' => $this->isGranted('ROLE_TECH'),
            'action' => $this->generateUrl('app_ticket_comment', ['id' => $ticket->getId()]),
            'method' => 'POST',
        ]);

        $comments = $this->getTicketComments($ticket, $entityManager);
        $attachments = $ticket->getAttachments();
        $statuses = $entityManager->getRepository(Status::class)->findAll();

        $assignableUsers = [];
        if ($this->isGranted('ROLE_MANAGER')) {
            $assignableUsers = $userRepository->findAssignableAgents();
        }

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'comment_form' => $commentForm->createView(),
            'comments' => $comments,
            'attachments' => $attachments,
            'statuses' => $statuses,
            'assignable_users' => $assignableUsers,
        ]);
    }

    #[Route('/ticket/{id}/comment', name: 'app_ticket_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function comment(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertCanAccessTicket($ticket);

        $comment = new TicketComment();
        $form = $this->createForm(TicketCommentType::class, $comment, [
            'allow_internal' => $this->isGranted('ROLE_TECH'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user) {
                throw $this->createAccessDeniedException('You must be logged in to comment.');
            }

            if (!$comment->getContent()) {
                $this->addFlash('warning', 'Comment cannot be empty.');
                return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
            }

            $comment->setTicket($ticket);
            $comment->setUser($user);
            if (!$this->isGranted('ROLE_TECH')) {
                $comment->setIsInternal(false);
            }

            $entityManager->persist($comment);
            $ticket->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Comment added.');
        } else {
            $this->addFlash('warning', 'Unable to add comment.');
        }

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/status', name: 'app_ticket_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');
        $this->assertCanAccessTicket($ticket);

        if (!$this->isCsrfTokenValid('ticket_status_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $statusId = $request->request->get('status_id');
        $status = $statusId ? $entityManager->getRepository(Status::class)->find($statusId) : null;

        if (!$status) {
            $this->addFlash('warning', 'Please choose a valid status.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        $ticket->setStatus($status);
        $ticket->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Ticket status updated.');

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/assign', name: 'app_ticket_assign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assign(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $this->assertCanAccessTicket($ticket);

        if (!$this->isCsrfTokenValid('ticket_assign_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $assigneeId = $request->request->get('assignee_id');
        $assignee = $assigneeId ? $userRepository->find($assigneeId) : null;

        if ($assignee && !in_array($assignee->getDbRole(), ['TECH', 'MANAGER'], true)) {
            $this->addFlash('warning', 'Selected user is not assignable.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        $ticket->setAssignee($assignee);
        $ticket->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Ticket assignment updated.');

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/pickup', name: 'app_ticket_pickup', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pickup(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');

        if (!$this->isCsrfTokenValid('ticket_pickup_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($ticket->getAssignee()) {
            $this->addFlash('warning', 'Ticket is already assigned.');
            return $this->redirectToRoute('app_dashboard');
        }

        $ticket->setAssignee($this->getUser());
        
        // Auto set status to 'En cours' if possible
        $status = $entityManager->getRepository(Status::class)->findOneBy(['label' => 'En cours']);
        if ($status) {
            $ticket->setStatus($status);
        }
        $ticket->setUpdatedAt(new \DateTime());
        
        $entityManager->flush();

        $this->addFlash('success', 'Ticket assigned to you.');
        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_update', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $displayName = trim((string) $request->request->get('display_name'));
            if ($displayName !== '') {
                $user->setName($displayName);
            } else {
                $this->addFlash('warning', 'Display name cannot be empty.');
            }

            $user->setNotifyEmail($request->request->has('notify_email'));
            $user->setNotifyDesktop($request->request->has('notify_desktop'));

            $theme = (string) $request->request->get('theme');
            if (in_array($theme, ['light', 'dark', 'system'], true)) {
                $user->setTheme($theme);
            } else {
                $this->addFlash('warning', 'Invalid theme selection.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Settings updated.');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings.html.twig');
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'])]
    public function editUser(User $user, Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminUserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $plainPassword
                    )
                );
            }

            if (!$user->getDbRole()) {
                $user->setDbRole('USER');
            }

            $entityManager->flush();
            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/edit_user.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    private function assertCanAccessTicket(Ticket $ticket): void
    {
        $user = $this->getUser();

        $canAccess = $this->isGranted('ROLE_TECH')
            || ($user && $ticket->getCreator() && $ticket->getCreator()->getId() === $user->getId())
            || ($user && $ticket->getAssignee() && $ticket->getAssignee()->getId() === $user->getId());

        if (!$canAccess) {
            throw $this->createAccessDeniedException('You cannot access this ticket.');
        }
    }

    private function getTicketComments(Ticket $ticket, EntityManagerInterface $entityManager): array
    {
        $criteria = ['ticket' => $ticket];
        if (!$this->isGranted('ROLE_TECH')) {
            $criteria['isInternal'] = false;
        }

        return $entityManager->getRepository(TicketComment::class)->findBy($criteria, ['createdAt' => 'ASC']);
    }

    private function calculateSlaDueAt(?Priority $priority): ?\DateTimeInterface
    {
        if (!$priority || $priority->getLevel() === null) {
            return null;
        }

        $hours = self::SLA_HOURS_BY_LEVEL[$priority->getLevel()] ?? 48;
        $dueAt = new \DateTime();
        $dueAt->modify('+' . $hours . ' hours');

        return $dueAt;
    }

    /**
     * @param UploadedFile[]|null $uploadedFiles
     */
    private function handleAttachments(Ticket $ticket, ?array $uploadedFiles, EntityManagerInterface $entityManager): void
    {
        if (!$uploadedFiles) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/tickets';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFile) {
                continue;
            }

            $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension();
            $fileName = bin2hex(random_bytes(8)) . ($extension ? '.' . $extension : '');
            $uploadedFile->move($uploadDir, $fileName);

            $attachment = new Attachment();
            $attachment->setTicket($ticket);
            $attachment->setFilePath('uploads/tickets/' . $fileName);
            $entityManager->persist($attachment);
        }

        $entityManager->flush();
    }

    private function parseDateFilter(?string $value, bool $endOfDay = false): ?\DateTimeInterface
    {
        if (!$value) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date) {
            return null;
        }

        if ($endOfDay) {
            $date->setTime(23, 59, 59);
        }

        return $date;
    }
}
