<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020215135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE perfume ADD list_price_cents INT UNSIGNED DEFAULT NULL, ADD list_price_currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL');
        $this->addSql('CREATE INDEX idx_perfume_price ON perfume (list_price_cents)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_perfume_price ON perfume');
        $this->addSql('ALTER TABLE perfume DROP list_price_cents, DROP list_price_currency');
    }
}
