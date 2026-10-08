<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008121631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue local Rebrickable (catalog_sets)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_sets (set_num VARCHAR(30) NOT NULL, numero VARCHAR(30) NOT NULL, name VARCHAR(255) NOT NULL, year INT DEFAULT NULL, theme_id INT DEFAULT NULL, theme_path VARCHAR(255) DEFAULT NULL, root_theme VARCHAR(100) DEFAULT NULL, parts INT DEFAULT NULL, img_url VARCHAR(255) DEFAULT NULL, vehicle BOOLEAN NOT NULL, PRIMARY KEY(set_num))');
        $this->addSql('CREATE INDEX idx_catalog_numero ON catalog_sets (numero)');
        $this->addSql('CREATE INDEX idx_catalog_vehicle_year ON catalog_sets (vehicle, year)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE catalog_sets');
    }
}
