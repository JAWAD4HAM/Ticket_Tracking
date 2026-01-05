<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104185411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `category-post` (id_category INT AUTO_INCREMENT NOT NULL, nom_category VARCHAR(100) NOT NULL, PRIMARY KEY (id_category)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE creation_ticket (id_ticket INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) DEFAULT NULL, Description LONGTEXT DEFAULT NULL, date_creation DATETIME DEFAULT NULL, id_category INT DEFAULT NULL, id_priority INT DEFAULT NULL, id_status INT DEFAULT NULL, id_user INT DEFAULT NULL, INDEX IDX_F9F775D65697F554 (id_category), INDEX IDX_F9F775D6327D30B2 (id_priority), INDEX IDX_F9F775D65D37D0F1 (id_status), INDEX IDX_F9F775D66B3CA4B (id_user), PRIMARY KEY (id_ticket)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE priorities (priorite_id INT AUTO_INCREMENT NOT NULL, lebelle VARCHAR(100) NOT NULL, PRIMARY KEY (priorite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE statuts (status_id INT AUTO_INCREMENT NOT NULL, lebelle VARCHAR(100) NOT NULL, PRIMARY KEY (status_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id_user INT AUTO_INCREMENT NOT NULL, email VARCHAR(150) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(100) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL, avatar_photo VARCHAR(255) DEFAULT NULL, role VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id_user)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE creation_ticket ADD CONSTRAINT FK_F9F775D65697F554 FOREIGN KEY (id_category) REFERENCES `category-post` (id_category)');
        $this->addSql('ALTER TABLE creation_ticket ADD CONSTRAINT FK_F9F775D6327D30B2 FOREIGN KEY (id_priority) REFERENCES priorities (priorite_id)');
        $this->addSql('ALTER TABLE creation_ticket ADD CONSTRAINT FK_F9F775D65D37D0F1 FOREIGN KEY (id_status) REFERENCES statuts (status_id)');
        $this->addSql('ALTER TABLE creation_ticket ADD CONSTRAINT FK_F9F775D66B3CA4B FOREIGN KEY (id_user) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY `fk_attachment_ticket`');
        $this->addSql('ALTER TABLE kb_article DROP FOREIGN KEY `fk_article_author`');
        $this->addSql('ALTER TABLE kb_article DROP FOREIGN KEY `fk_article_category`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_assignee`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_category`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_creator`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_priority`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_status`');
        $this->addSql('ALTER TABLE ticket_comment DROP FOREIGN KEY `fk_comment_ticket`');
        $this->addSql('ALTER TABLE ticket_comment DROP FOREIGN KEY `fk_comment_user`');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE kb_article');
        $this->addSql('DROP TABLE priority');
        $this->addSql('DROP TABLE status');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ticket_comment');
        $this->addSql('DROP TABLE user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, ticket_id INT NOT NULL, INDEX fk_attachment_ticket (ticket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE kb_article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, is_published TINYINT DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, author_id INT NOT NULL, category_id INT DEFAULT NULL, INDEX fk_article_author (author_id), INDEX fk_article_category (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE priority (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, level INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE status (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, creator_id INT NOT NULL, assignee_id INT DEFAULT NULL, category_id INT NOT NULL, priority_id INT NOT NULL, status_id INT NOT NULL, sla_due_at DATETIME DEFAULT NULL COMMENT \'Date/Time when the ticket must be resolved\', INDEX fk_ticket_category (category_id), INDEX fk_ticket_status (status_id), INDEX fk_ticket_priority (priority_id), INDEX fk_ticket_creator (creator_id), INDEX fk_ticket_assignee (assignee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ticket_comment (id INT AUTO_INCREMENT NOT NULL, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, ticket_id INT NOT NULL, user_id INT NOT NULL, INDEX fk_comment_ticket (ticket_id), INDEX fk_comment_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, email VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, role VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE INDEX email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT `fk_attachment_ticket` FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE kb_article ADD CONSTRAINT `fk_article_author` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE kb_article ADD CONSTRAINT `fk_article_category` FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_assignee` FOREIGN KEY (assignee_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_category` FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_creator` FOREIGN KEY (creator_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_priority` FOREIGN KEY (priority_id) REFERENCES priority (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_status` FOREIGN KEY (status_id) REFERENCES status (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket_comment ADD CONSTRAINT `fk_comment_ticket` FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ticket_comment ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE creation_ticket DROP FOREIGN KEY FK_F9F775D65697F554');
        $this->addSql('ALTER TABLE creation_ticket DROP FOREIGN KEY FK_F9F775D6327D30B2');
        $this->addSql('ALTER TABLE creation_ticket DROP FOREIGN KEY FK_F9F775D65D37D0F1');
        $this->addSql('ALTER TABLE creation_ticket DROP FOREIGN KEY FK_F9F775D66B3CA4B');
        $this->addSql('DROP TABLE `category-post`');
        $this->addSql('DROP TABLE creation_ticket');
        $this->addSql('DROP TABLE priorities');
        $this->addSql('DROP TABLE statuts');
        $this->addSql('DROP TABLE utilisateur');
    }
}
