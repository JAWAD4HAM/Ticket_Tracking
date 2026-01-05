<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Priority;
use App\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Seeds the database with initial categories, priorities, and statuses',
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Priorities
        $priorities = [
            'P1 - Urgent' => 1,
            'P2 - High' => 2,
            'P3 - Normal' => 3,
            'P4 - Low' => 4
        ];
        foreach ($priorities as $label => $level) {
            if (!$this->entityManager->getRepository(Priority::class)->findOneBy(['label' => $label])) {
                $priority = new Priority();
                $priority->setLabel($label);
                $priority->setLevel($level);
                $this->entityManager->persist($priority);
            }
        }

        // Categories
        $categories = ['Hardware', 'Software', 'Network', 'Access Request', 'General Inquiry'];
        foreach ($categories as $label) {
            if (!$this->entityManager->getRepository(Category::class)->findOneBy(['label' => $label])) {
                $category = new Category();
                $category->setLabel($label);
                $this->entityManager->persist($category);
            }
        }

        // Statuses
        $statuses = ['Ouvert', 'En cours', 'Résolu', 'Fermé'];
        foreach ($statuses as $label) {
            if (!$this->entityManager->getRepository(Status::class)->findOneBy(['label' => $label])) {
                $status = new Status();
                $status->setLabel($label);
                $this->entityManager->persist($status);
            }
        }

        $this->entityManager->flush();

        $io->success('Database seeded successfully!');

        return Command::SUCCESS;
    }
}
