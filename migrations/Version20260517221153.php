<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517221153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seasons and occasions display context to perfume.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE perfume ADD seasons JSON DEFAULT NULL, ADD occasions JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE perfume DROP seasons, DROP occasions');
    }
}
