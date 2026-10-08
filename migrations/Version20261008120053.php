<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma Collection LEGO.
 *
 * Idempotente : sur une base neuve elle crée tout, sur la base de l'ancienne version
 * (créée avec doctrine:schema:update, sans migrations) elle ajoute seulement les nouvelles
 * colonnes et conserve toutes les données.
 */
final class Version20261008120053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma Collection LEGO : sets enrichis, réservations avec code d\'annulation (compatible ancienne base)';
    }

    public function up(Schema $schema): void
    {
        // --- sets
        $this->addSql('CREATE TABLE IF NOT EXISTS sets (id SERIAL NOT NULL, numero_set VARCHAR(20) NOT NULL, nom VARCHAR(255) NOT NULL, theme VARCHAR(100) DEFAULT NULL, annee INT DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, owned BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS pieces INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS prix NUMERIC(8, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS priorite SMALLINT DEFAULT 2 NOT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS added_by_giver BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sets ADD COLUMN IF NOT EXISTS owned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_948D45D1D343A36B ON sets (numero_set)');
        $this->addSql('COMMENT ON COLUMN sets.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sets.owned_at IS \'(DC2Type:datetime_immutable)\'');
        // Les sets déjà présents datent d'avant la migration
        $this->addSql('UPDATE sets SET created_at = NOW() WHERE created_at IS NULL');

        // --- reservations
        $this->addSql('CREATE TABLE IF NOT EXISTS reservations (id SERIAL NOT NULL, set_id INT NOT NULL, reserved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reserved_by_hash VARCHAR(255) DEFAULT NULL, anonymous_id VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE reservations ADD COLUMN IF NOT EXISTS cancel_code_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_set_reservation ON reservations (set_id)');
        $this->addSql('COMMENT ON COLUMN reservations.reserved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_4da23910fb0d18\') THEN
                ALTER TABLE reservations ADD CONSTRAINT FK_4DA23910FB0D18 FOREIGN KEY (set_id) REFERENCES sets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
            END IF;
        END $$');

        // --- messenger
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
    }

    public function down(Schema $schema): void
    {
        // Retour au schéma de l'ancienne version, sans perdre les sets
        $this->addSql('ALTER TABLE reservations DROP COLUMN IF EXISTS cancel_code_hash');
        $this->addSql('ALTER TABLE sets DROP COLUMN IF EXISTS pieces, DROP COLUMN IF EXISTS prix, DROP COLUMN IF EXISTS priorite, DROP COLUMN IF EXISTS notes, DROP COLUMN IF EXISTS added_by_giver, DROP COLUMN IF EXISTS created_at, DROP COLUMN IF EXISTS owned_at');
    }
}
