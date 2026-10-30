<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029162522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the public website directory for anonymous free scans.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE public_site (id INT AUTO_INCREMENT NOT NULL, domain VARCHAR(191) NOT NULL, slug VARCHAR(191) NOT NULL, name VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, niche VARCHAR(80) DEFAULT NULL, score INT NOT NULL, checks JSON NOT NULL, status VARCHAR(20) NOT NULL, scanned_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_121E4B35A7A91E0B (domain), UNIQUE INDEX UNIQ_121E4B35989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE public_site');
    }
}
