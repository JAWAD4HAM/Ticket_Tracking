<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelpdeskController extends AbstractController
{


     #[Route('/', name: 'app_dashboard')]

    public function dashboard(): Response
    {

        return $this->render('dashboard/dashboard.html.twig');

    }





    #[Route('/ticket/create', name: 'app_ticket_create')]

    public function createTicket(): Response
    {
        return $this->render('create_ticket.html.twig');
    }

    
    #[Route('/ticket', name: 'app_ticket')]

    public function ticket(): Response
    {
        return $this->render('ticket.html.twig');
    }

   



    #[Route('/agents', name: 'app_agents')]
    public function agents(): Response
    {
        return $this->render('agents.html.twig');
    }

    #[Route('/user/create', name: 'app_user_create')]
    public function createUser(): Response
    {
        return $this->render('adduser.html.twig');
    }

    #[Route('/ticket/assign', name: 'app_ticket_assign')]
    public function assignTicket(): Response
    {
        return $this->render('ticket_assignement.html.twig');
    }


}