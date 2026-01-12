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
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
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
    public function dashboard(TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // If not logged in, show generic welcome or prompt login
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $userRepo = $entityManager->getRepository(User::class);
            $categoryRepo = $entityManager->getRepository(\App\Entity\Category::class);
            
            $adminStats = [
                'total_users' => $userRepo->countAll(),
                'tech_users' => $userRepo->countByRole('TECH'),
                'active_categories' => $categoryRepo->count([]), 
            ];
            
            $ticketStats = $ticketRepository->getAdminStats();
            
            // Fetch Urgent Priority ID
            $priorityRepo = $entityManager->getRepository(Priority::class);
            $urgentPriority = $priorityRepo->findOneBy(['label' => 'Urgent']);

            $ticketStats = $ticketRepository->getAdminStats();
            
            // Fetch Urgent Priority ID
            $priorityRepo = $entityManager->getRepository(Priority::class);
            $urgentPriority = $priorityRepo->findOneBy(['label' => 'Urgent']);

            return $this->render('dashboard/admin.html.twig', [
                'stats' => array_merge($adminStats, $ticketStats),
                'urgent_priority_id' => $urgentPriority ? $urgentPriority->getId() : null,
                'simple_mode' => true, 
            ]);
        }

        if ($this->isGranted('ROLE_MANAGER')) {
            $request = $this->container->get('request_stack')->getCurrentRequest();
            $period = $request->query->get('period', '7_days');
            $startDate = null;
            $endDate = new \DateTime('now');

            switch ($period) {
                case '7_days':
                    $startDate = new \DateTime('-7 days');
                    break;
                case '30_days':
                    $startDate = new \DateTime('-30 days');
                    break;
                case 'this_month':
                    $startDate = new \DateTime('first day of this month 00:00:00');
                    break;
                case 'last_month':
                    $startDate = new \DateTime('first day of last month 00:00:00');
                    $endDate = new \DateTime('last day of last month 23:59:59');
                    break;
                case 'all_time':
                    $startDate = null;
                    break;
                default:
                    // Default to 7 days if invalid
                    $startDate = new \DateTime('-7 days');
            }
            if ($startDate) $startDate->setTime(0, 0, 0);
            if ($endDate) $endDate->setTime(23, 59, 59);

            $stats = $ticketRepository->getManagerStats($startDate, $endDate);
            $volumeData = $ticketRepository->getTicketVolumeOverTime($startDate, $endDate);
            $statusData = $ticketRepository->getStatusDistribution($startDate, $endDate);

            return $this->render('dashboard/manager.html.twig', [
                'stats' => $stats,
                'recent_tickets' => $ticketRepository->findAllSorted(10),
                'chart_data' => [
                    'volume' => $volumeData,
                    'status' => $statusData
                ],
                'current_period' => $period,
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
            'deleted_tickets' => $ticketRepository->findDeletedByCreator($user),
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

            // Set a default status with a sensible fallback if localized labels differ.
            $statusRepository = $entityManager->getRepository(Status::class);
            $status = $statusRepository->findOneBy(['label' => 'Ouvert'])
                ?? $statusRepository->findOneBy(['label' => 'Open'])
                ?? $statusRepository->findOneBy([], ['id' => 'ASC']);

            if (!$status) {
                $form->addError(new FormError('Aucun statut trouvé. Veuillez ajouter un statut dans les paramètres administrateur.'));
            } else {
                $ticket->setStatus($status);
                $ticket->setSlaDueAt($this->calculateSlaDueAt($ticket->getPriority()));
                $entityManager->persist($ticket);
                $entityManager->flush();

                $this->handleAttachments($ticket, $form->get('attachments')->getData(), $entityManager);

                $this->addFlash('success', 'Ticket créé avec succès !');

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

        $assigneeFilter = $request->query->get('assignee');

        $fromDate = $this->parseDateFilter($fromFilter);
        $toDate = $this->parseDateFilter($toFilter, true);

        $tickets = $ticketRepository->findFiltered(
            $statusFilter ?: null,
            $priorityFilter ? (int) $priorityFilter : null,
            $fromDate,
            $toDate,
            $assigneeFilter
        );

        $statuses = $entityManager->getRepository(Status::class)->findAll();
        $priorities = $entityManager->getRepository(Priority::class)->findAll();
        $deletedTickets = $this->isGranted('ROLE_ADMIN')
            ? $ticketRepository->findDeletedAll()
            : $ticketRepository->findDeletedByCreator($this->getUser());

        return $this->render('ticket.html.twig', [
            'tickets' => $tickets,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'deleted_tickets' => $deletedTickets,
            'simple_mode' => $request->query->get('simple_mode'),
            'page_title' => $assigneeFilter === 'unassigned' ? 'Unassigned Tickets' : ($priorityFilter ? 'Urgent Tickets' : 'All Tickets'),
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
            
            $this->addFlash('success', 'Utilisateur créé avec succès !');

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
        $this->assertTicketNotDeleted($ticket);

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
                $this->addFlash('warning', 'Le commentaire ne peut pas être vide.');
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

            $this->addFlash('success', 'Commentaire ajouté.');
        } else {
            $this->addFlash('warning', 'Impossible d\'ajouter le commentaire.');
        }

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/status', name: 'app_ticket_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');
        $this->assertCanAccessTicket($ticket);
        $this->assertTicketNotDeleted($ticket);

        if (!$this->isCsrfTokenValid('ticket_status_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $statusId = $request->request->get('status_id');
        $status = $statusId ? $entityManager->getRepository(Status::class)->find($statusId) : null;

        if (!$status) {
            $this->addFlash('warning', 'Veuillez choisir un statut valide.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        $ticket->setStatus($status);
        $ticket->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Statut du ticket mis à jour.');

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/assign', name: 'app_ticket_assign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assign(Ticket $ticket, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $this->assertCanAccessTicket($ticket);
        $this->assertTicketNotDeleted($ticket);

        if (!$this->isCsrfTokenValid('ticket_assign_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $assigneeId = $request->request->get('assignee_id');
        $assignee = $assigneeId ? $userRepository->find($assigneeId) : null;

        if ($assignee && !in_array($assignee->getDbRole(), ['TECH', 'MANAGER'], true)) {
            $this->addFlash('warning', 'L\'utilisateur sélectionné n\'est pas assignable.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        $ticket->setAssignee($assignee);
        $ticket->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Affectation du ticket mise à jour.');

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/pickup', name: 'app_ticket_pickup', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pickup(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');

        if (!$this->isCsrfTokenValid('ticket_pickup_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->assertTicketNotDeleted($ticket);

        if ($ticket->getAssignee()) {
            $this->addFlash('warning', 'Le ticket est déjà assigné.');
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

        $this->addFlash('success', 'Ticket assigné à vous.');
        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/delete', name: 'app_ticket_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTicket(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('ticket_delete_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $canDelete = $this->isGranted('ROLE_ADMIN')
            || ($ticket->getCreator() && $ticket->getCreator()->getId() === $user->getId());

        if (!$canDelete) {
            throw $this->createAccessDeniedException('You cannot delete this ticket.');
        }

        if ($ticket->getDeletedAt()) {
            $this->addFlash('warning', 'Le ticket est déjà dans la corbeille.');
        } else {
            $ticket->setDeletedAt(new \DateTime());
            $ticket->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Ticket déplacé dans la corbeille.');
        }

        $redirectRoute = $this->isGranted('ROLE_TECH') ? 'app_ticket' : 'app_dashboard';
        return $this->redirectToRoute($redirectRoute);
    }

    #[Route('/ticket/{id}/restore', name: 'app_ticket_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restoreTicket(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('ticket_restore_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $canRestore = $this->isGranted('ROLE_ADMIN')
            || ($ticket->getCreator() && $ticket->getCreator()->getId() === $user->getId());

        if (!$canRestore) {
            throw $this->createAccessDeniedException('You cannot restore this ticket.');
        }

        if (!$ticket->getDeletedAt()) {
            $this->addFlash('warning', 'Le ticket n\'est pas dans la corbeille.');
        } else {
            $ticket->setDeletedAt(null);
            $ticket->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Ticket restauré.');
        }

        $redirectRoute = $this->isGranted('ROLE_TECH') ? 'app_ticket' : 'app_dashboard';
        return $this->redirectToRoute($redirectRoute);
    }

    #[Route('/ticket/{id}/delete-permanent', name: 'app_ticket_delete_permanent', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTicketPermanently(Ticket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('ticket_delete_permanent_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $canDelete = $this->isGranted('ROLE_ADMIN')
            || ($ticket->getCreator() && $ticket->getCreator()->getId() === $user->getId());

        if (!$canDelete) {
            throw $this->createAccessDeniedException('You cannot delete this ticket.');
        }

        if (!$ticket->getDeletedAt()) {
            $this->addFlash('warning', 'Le ticket doit d\'abord être dans la corbeille.');
        } else {
            $this->purgeTicket($ticket, $entityManager);
            $entityManager->flush();
            $this->addFlash('success', 'Ticket supprimé définitivement.');
        }

        $redirectRoute = $this->isGranted('ROLE_TECH') ? 'app_ticket' : 'app_dashboard';
        return $this->redirectToRoute($redirectRoute);
    }

    #[Route('/ticket/trash/restore', name: 'app_ticket_restore_all', methods: ['POST'])]
    public function restoreAllTickets(Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('ticket_restore_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $tickets = $this->isGranted('ROLE_ADMIN')
            ? $ticketRepository->findDeletedAll()
            : $ticketRepository->findDeletedByCreator($user);

        if (!$tickets) {
            $this->addFlash('warning', 'La corbeille est déjà vide.');
        } else {
            foreach ($tickets as $ticket) {
                $ticket->setDeletedAt(null);
                $ticket->setUpdatedAt(new \DateTime());
            }
            $entityManager->flush();
            $this->addFlash('success', 'Tous les tickets ont été restaurés.');
        }

        $redirectRoute = $this->isGranted('ROLE_TECH') ? 'app_ticket' : 'app_dashboard';
        return $this->redirectToRoute($redirectRoute);
    }

    #[Route('/ticket/trash/empty', name: 'app_ticket_empty_trash', methods: ['POST'])]
    public function emptyTrash(Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('ticket_empty_trash', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $tickets = $this->isGranted('ROLE_ADMIN')
            ? $ticketRepository->findDeletedAll()
            : $ticketRepository->findDeletedByCreator($user);

        if (!$tickets) {
            $this->addFlash('warning', 'La corbeille est déjà vide.');
        } else {
            foreach ($tickets as $ticket) {
                $this->purgeTicket($ticket, $entityManager);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Corbeille vidée.');
        }

        $redirectRoute = $this->isGranted('ROLE_TECH') ? 'app_ticket' : 'app_dashboard';
        return $this->redirectToRoute($redirectRoute);
    }

    #[Route('/settings/password', name: 'app_settings_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if (!$this->isCsrfTokenValid('settings_password', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $currentPassword = (string) $request->request->get('current_password');
        $newPassword = (string) $request->request->get('new_password');
        $confirmPassword = (string) $request->request->get('confirm_password');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_settings');
        }

        if (empty($newPassword) || strlen($newPassword) < 6) {
            $this->addFlash('error', 'Le nouveau mot de passe doit comporter au moins 6 caractères.');
            return $this->redirectToRoute('app_settings');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_settings');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');

        return $this->redirectToRoute('app_settings');
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
                $this->addFlash('warning', 'Le nom d\'affichage ne peut pas être vide.');
            }



            $entityManager->flush();
            $this->addFlash('success', 'Paramètres mis à jour.');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings.html.twig');
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $roleFilter = $request->query->get('role');
        $users = $roleFilter 
            ? $userRepository->findBy(['role' => strtoupper($roleFilter)]) 
            : $userRepository->findAll();

        $pageTitle = 'User Management';
        if ($roleFilter === 'TECH') {
            $pageTitle = 'Technicians';
        } elseif ($request->query->get('simple_mode')) {
            $pageTitle = 'Total Users';
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'simple_mode' => $request->query->get('simple_mode'),
            'page_title' => $pageTitle,
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
            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/edit_user.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('user_delete_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_users');
        }

        try {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'L\'utilisateur est assigné à des tickets et ne peut pas être supprimé.');
        }

        return $this->redirectToRoute('app_admin_users');
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

    private function assertTicketNotDeleted(Ticket $ticket): void
    {
        if ($ticket->getDeletedAt() !== null) {
            throw $this->createAccessDeniedException('Ticket is in the trash.');
        }
    }

    private function purgeTicket(Ticket $ticket, EntityManagerInterface $entityManager): void
    {
        foreach ($ticket->getAttachments()->toArray() as $attachment) {
            $path = $this->getParameter('kernel.project_dir') . '/public/' . $attachment->getFilePath();
            if (is_file($path)) {
                @unlink($path);
            }
            $entityManager->remove($attachment);
        }

        foreach ($ticket->getComments()->toArray() as $comment) {
            $entityManager->remove($comment);
        }

        $entityManager->remove($ticket);
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
