<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove user notification preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP notify_email');
        $this->addSql('ALTER TABLE user DROP notify_desktop');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD notify_email TINYINT(1) DEFAULT 1 NOT NULL");
        $this->addSql("ALTER TABLE user ADD notify_desktop TINYINT(1) DEFAULT 1 NOT NULL");
    }
}
