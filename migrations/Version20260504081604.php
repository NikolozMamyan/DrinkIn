<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504081604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_session (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, device_id VARCHAR(64) NOT NULL, device_name VARCHAR(120) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, expires_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, is_revoked TINYINT NOT NULL, revoked_at DATETIME DEFAULT NULL, revoked_reason VARCHAR(120) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8849CBDEA76ED395 (user_id), INDEX idx_user_session_device_id (device_id), INDEX idx_user_session_expires_at (expires_at), UNIQUE INDEX uniq_user_session_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDEA76ED395');
        $this->addSql('DROP TABLE user_session');
    }
}
