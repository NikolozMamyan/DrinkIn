<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow guest orders and store guest checkout details.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD guest_email VARCHAR(180) DEFAULT NULL, ADD guest_first_name VARCHAR(100) DEFAULT NULL, ADD guest_last_name VARCHAR(100) DEFAULT NULL, ADD guest_phone VARCHAR(30) DEFAULT NULL, ADD guest_street VARCHAR(160) DEFAULT NULL, ADD guest_postal_code VARCHAR(20) DEFAULT NULL, ADD guest_city VARCHAR(120) DEFAULT NULL, ADD guest_country_code VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE `order` DROP guest_email, DROP guest_first_name, DROP guest_last_name, DROP guest_phone, DROP guest_street, DROP guest_postal_code, DROP guest_city, DROP guest_country_code');
        $this->addSql('DELETE FROM `order` WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE `order` CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }
}
