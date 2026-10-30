<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027194735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scan ADD public_id VARCHAR(32) DEFAULT NULL, ADD failure_reason LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4B3B3AEB5B48B91 ON scan (public_id)');

        // Existing scans need a token before the column can become NOT NULL in the next migration.
        foreach ($this->connection->fetchFirstColumn('SELECT id FROM scan') as $id) {
            $this->addSql('UPDATE scan SET public_id = ? WHERE id = ?', [bin2hex(random_bytes(16)), $id]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_C4B3B3AEB5B48B91 ON scan');
        $this->addSql('ALTER TABLE scan DROP public_id, DROP failure_reason');
    }
}
