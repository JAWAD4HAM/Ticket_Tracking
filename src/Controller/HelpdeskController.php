<?php

namespace App\Controller;

use App\Service\Database;
use DateTimeImmutable;
use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelpdeskController extends AbstractController
{
    private Database $database;
    private string $uploadDir;

    public function __construct(
        Database $database,
        #[Autowire('%app.upload_dir%')] string $uploadDir
    ) {
        $this->database = $database;
        $this->uploadDir = $uploadDir;
    }

    #[Route('/', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        $connection = $this->database->getConnection();
        $totalTickets = (int) $connection->query('SELECT COUNT(*) FROM ticket')->fetchColumn();

        $statusRows = $connection->query(
            'SELECT status.label, COUNT(ticket.id) AS total
             FROM status
             LEFT JOIN ticket ON ticket.status_id = status.id
             GROUP BY status.id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $statusCounts = [];
        foreach ($statusRows as $row) {
            $statusCounts[strtolower((string) $row['label'])] = (int) $row['total'];
        }

        $recentTickets = $connection->query(
            'SELECT ticket.id, ticket.title, ticket.created_at, status.label AS status_label, user.name AS assignee_name
             FROM ticket
             LEFT JOIN user ON user.id = ticket.assignee_id
             LEFT JOIN status ON status.id = ticket.status_id
             ORDER BY ticket.created_at DESC
             LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('dashboard/dashboard.html.twig', [
            'totalTickets' => $totalTickets,
            'incomingTickets' => $statusCounts['open'] ?? $statusCounts['new'] ?? 0,
            'pendingTickets' => $statusCounts['pending'] ?? 0,
            'assignedTickets' => $statusCounts['in progress'] ?? $statusCounts['assigned'] ?? 0,
            'plannedTickets' => $statusCounts['planned'] ?? 0,
            'solvedTickets' => $statusCounts['resolved'] ?? 0,
            'closedTickets' => $statusCounts['closed'] ?? 0,
            'recentTickets' => $recentTickets,
        ]);
    }

    #[Route('/ticket/create', name: 'app_ticket_create')]
    public function createTicket(): Response
    {
        return $this->render('create_ticket.html.twig', $this->loadTicketFormData());
    }

    #[Route('/ticket', name: 'app_ticket')]
    public function ticket(): Response
    {
        $connection = $this->database->getConnection();
        $tickets = $connection->query(
            'SELECT ticket.id, ticket.title, ticket.created_at, status.label AS status_label, user.name AS assignee_name
             FROM ticket
             LEFT JOIN user ON user.id = ticket.assignee_id
             LEFT JOIN status ON status.id = ticket.status_id
             ORDER BY ticket.created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('ticket.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    #[Route('/agents', name: 'app_agents')]
    public function agents(): Response
    {
        $connection = $this->database->getConnection();
        $agents = $connection->prepare('SELECT id, name, email, role FROM user WHERE role = :role ORDER BY name');
        $agents->execute(['role' => 'agent']);

        return $this->render('agents.html.twig', [
            'agents' => $agents->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    #[Route('/user/create', name: 'app_user_create')]
    public function createUser(): Response
    {
        return $this->render('adduser.html.twig');
    }

    #[Route('/ticket/assign', name: 'app_ticket_assign')]
    public function assignTicket(): Response
    {
        $connection = $this->database->getConnection();
        $tickets = $connection->query('SELECT id, title FROM ticket ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        $agents = $connection->prepare('SELECT id, name FROM user WHERE role = :role ORDER BY name');
        $agents->execute(['role' => 'agent']);

        return $this->render('ticket_assignement.html.twig', [
            'tickets' => $tickets,
            'agents' => $agents->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    #[Route('/settings', name: 'app_settings')]
    public function settings(): Response
    {
        return $this->render('settings.html.twig');
    }

    #[Route('/submit-ticket', name: 'app_ticket_submit', methods: ['POST'])]
    public function submitTicket(Request $request): Response
    {
        $title = trim((string) $request->request->get('titre'));
        $description = trim((string) $request->request->get('Description'));
        $categoryId = (int) $request->request->get('category_id');
        $priorityId = (int) $request->request->get('priority_id');
        $creatorId = (int) $request->request->get('creator_id');
        $createdAt = $request->request->get('date_creation');

        if ($title === '' || $description === '' || $categoryId === 0 || $priorityId === 0 || $creatorId === 0) {
            return $this->render('create_ticket.html.twig', array_merge(
                ['error' => 'Please fill in all required fields.'],
                $this->loadTicketFormData()
            ));
        }

        $connection = $this->database->getConnection();
        $statusId = $this->findStatusId($connection, ['Open', 'New']) ?? $this->findAnyStatusId($connection);
        if ($statusId === null) {
            return new Response('No ticket status configured in the database.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $createdAtValue = $createdAt ? new DateTimeImmutable($createdAt) : new DateTimeImmutable();

        $statement = $connection->prepare(
            'INSERT INTO ticket (title, description, creator_id, category_id, priority_id, status_id, created_at, updated_at)
             VALUES (:title, :description, :creator_id, :category_id, :priority_id, :status_id, :created_at, :updated_at)'
        );

        $statement->execute([
            'title' => $title,
            'description' => $description,
            'creator_id' => $creatorId,
            'category_id' => $categoryId,
            'priority_id' => $priorityId,
            'status_id' => $statusId,
            'created_at' => $createdAtValue->format('Y-m-d H:i:s'),
            'updated_at' => $createdAtValue->format('Y-m-d H:i:s'),
        ]);

        $ticketId = (int) $connection->lastInsertId();

        $file = $request->files->get('chemin_fichier');
        if ($file) {
            if ($file->getSize() > 10 * 1024 * 1024) {
                return new Response('Attachment exceeds 10MB limit.', Response::HTTP_BAD_REQUEST);
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'log'];
            if (!in_array($extension, $allowedExtensions, true)) {
                return new Response('Attachment type is not allowed.', Response::HTTP_BAD_REQUEST);
            }

            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0775, true);
            }

            $safeName = sprintf('%s-%s.%s', $ticketId, uniqid('', true), $extension);
            $file->move($this->uploadDir, $safeName);

            $attachmentStatement = $connection->prepare(
                'INSERT INTO attachment (file_path, ticket_id) VALUES (:file_path, :ticket_id)'
            );
            $attachmentStatement->execute([
                'file_path' => sprintf('uploads/%s', $safeName),
                'ticket_id' => $ticketId,
            ]);
        }

        return new RedirectResponse($this->generateUrl('app_ticket'));
    }

    #[Route('/submit-agent', name: 'app_agent_submit', methods: ['POST'])]
    public function submitAgent(Request $request): Response
    {
        return $this->submitUserWithRole($request, 'agent', 'app_agents');
    }

    #[Route('/submit-user', name: 'app_user_submit', methods: ['POST'])]
    public function submitUser(Request $request): Response
    {
        $role = (string) $request->request->get('role');
        $role = $role !== '' ? $role : 'user';

        return $this->submitUserWithRole($request, $role, 'app_user_create');
    }

    #[Route('/submit-assignment', name: 'app_assignment_submit', methods: ['POST'])]
    public function submitAssignment(Request $request): Response
    {
        $ticketId = (int) $request->request->get('ticket_id');
        $agentId = (int) $request->request->get('agent_id');

        if ($ticketId === 0 || $agentId === 0) {
            return new Response('Ticket and agent are required.', Response::HTTP_BAD_REQUEST);
        }

        $connection = $this->database->getConnection();
        $statusId = $this->findStatusId($connection, ['In Progress', 'Assigned']);

        $statement = $connection->prepare(
            'UPDATE ticket SET assignee_id = :assignee_id, status_id = COALESCE(:status_id, status_id) WHERE id = :ticket_id'
        );
        $statement->execute([
            'assignee_id' => $agentId,
            'status_id' => $statusId,
            'ticket_id' => $ticketId,
        ]);

        return new RedirectResponse($this->generateUrl('app_ticket'));
    }

    private function submitUserWithRole(Request $request, string $role, string $redirectRoute): Response
    {
        $name = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $password = (string) $request->request->get('password');

        if ($name === '' || $email === '' || $password === '') {
            return new Response('Name, email, and password are required.', Response::HTTP_BAD_REQUEST);
        }

        $connection = $this->database->getConnection();
        $statement = $connection->prepare(
            'INSERT INTO user (name, email, password, role) VALUES (:name, :email, :password, :role)'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
        ]);

        return new RedirectResponse($this->generateUrl($redirectRoute));
    }

    private function findStatusId(PDO $connection, array $labels): ?int
    {
        foreach ($labels as $label) {
            $statement = $connection->prepare('SELECT id FROM status WHERE label = :label LIMIT 1');
            $statement->execute(['label' => $label]);
            $statusId = $statement->fetchColumn();
            if ($statusId !== false) {
                return (int) $statusId;
            }
        }

        return null;
    }

    private function findAnyStatusId(PDO $connection): ?int
    {
        $statusId = $connection->query('SELECT id FROM status ORDER BY id LIMIT 1')->fetchColumn();
        return $statusId === false ? null : (int) $statusId;
    }

    private function loadTicketFormData(): array
    {
        $connection = $this->database->getConnection();

        return [
            'categories' => $connection->query('SELECT id, label FROM category ORDER BY label')->fetchAll(PDO::FETCH_ASSOC),
            'priorities' => $connection->query('SELECT id, label FROM priority ORDER BY level DESC')->fetchAll(PDO::FETCH_ASSOC),
            'users' => $connection->query('SELECT id, name FROM user ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
