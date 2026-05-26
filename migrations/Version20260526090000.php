<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stable partner slugs to brands.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE brand ADD partner_slug VARCHAR(180) DEFAULT NULL');

        $usedSlugs = [];
        $brands = $this->connection->fetchAllAssociative('SELECT id, name FROM brand ORDER BY id ASC');

        foreach ($brands as $brand) {
            $slug = $this->makeUniqueSlug((string) $brand['name'], $usedSlugs);

            $this->addSql('UPDATE brand SET partner_slug = ? WHERE id = ?', [$slug, $brand['id']]);
        }

        $this->addSql('CREATE UNIQUE INDEX uniq_brand_partner_slug ON brand (partner_slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_brand_partner_slug ON brand');
        $this->addSql('ALTER TABLE brand DROP partner_slug');
    }

    /**
     * @param array<string,true> $usedSlugs
     */
    private function makeUniqueSlug(string $name, array &$usedSlugs): string
    {
        $base = $this->slugify($name);
        $candidate = $base;
        $suffix = 2;

        while (isset($usedSlugs[$candidate])) {
            $suffixText = '-'.$suffix;
            $candidate = mb_substr($base, 0, 180 - strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        $usedSlugs[$candidate] = true;

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = $value !== '' ? $value : 'brand';

        return mb_substr($value, 0, 180);
    }
}
