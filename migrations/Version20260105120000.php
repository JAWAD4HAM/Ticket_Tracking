<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internal comment flag to ticket comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_comment ADD is_internal BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_comment DROP is_internal');
    }
}
