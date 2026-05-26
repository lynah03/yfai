<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add partner API key storage for B2B server-to-server access.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE partner_api_key (id INT AUTO_INCREMENT NOT NULL, brand_id INT NOT NULL, key_prefix VARCHAR(32) NOT NULL, key_hash VARCHAR(255) NOT NULL, label VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_partner_api_key_brand (brand_id), UNIQUE INDEX uniq_partner_api_key_prefix (key_prefix), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE partner_api_key ADD CONSTRAINT fk_partner_api_key_brand FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE partner_api_key DROP FOREIGN KEY fk_partner_api_key_brand');
        $this->addSql('DROP TABLE partner_api_key');
    }
}
