<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109233622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accord (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_91361A0477153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE perfume_accord (perfume_id INT NOT NULL, accord_id INT NOT NULL, INDEX IDX_883644D6AA91F2AA (perfume_id), INDEX IDX_883644D61EDF023F (accord_id), PRIMARY KEY(perfume_id, accord_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_accord_preference (id INT AUTO_INCREMENT NOT NULL, user_profile_id INT NOT NULL, accord_id INT NOT NULL, weight SMALLINT NOT NULL, INDEX IDX_F86994946B9DD454 (user_profile_id), INDEX IDX_F86994941EDF023F (accord_id), UNIQUE INDEX uniq_user_accord_pref (user_profile_id, accord_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE perfume_accord ADD CONSTRAINT FK_883644D6AA91F2AA FOREIGN KEY (perfume_id) REFERENCES perfume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE perfume_accord ADD CONSTRAINT FK_883644D61EDF023F FOREIGN KEY (accord_id) REFERENCES accord (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_accord_preference ADD CONSTRAINT FK_F86994946B9DD454 FOREIGN KEY (user_profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_accord_preference ADD CONSTRAINT FK_F86994941EDF023F FOREIGN KEY (accord_id) REFERENCES accord (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE perfume_accord DROP FOREIGN KEY FK_883644D6AA91F2AA');
        $this->addSql('ALTER TABLE perfume_accord DROP FOREIGN KEY FK_883644D61EDF023F');
        $this->addSql('ALTER TABLE user_accord_preference DROP FOREIGN KEY FK_F86994946B9DD454');
        $this->addSql('ALTER TABLE user_accord_preference DROP FOREIGN KEY FK_F86994941EDF023F');
        $this->addSql('DROP TABLE accord');
        $this->addSql('DROP TABLE perfume_accord');
        $this->addSql('DROP TABLE user_accord_preference');
    }
}
