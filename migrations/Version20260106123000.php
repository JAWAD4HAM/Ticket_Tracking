<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete support for tickets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP deleted_at');
    }
}
