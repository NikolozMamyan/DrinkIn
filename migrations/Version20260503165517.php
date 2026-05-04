<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503165517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_category_slug ON category (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_number ON `order` (order_number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_slug ON product (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON user (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_category_slug ON category');
        $this->addSql('DROP INDEX uniq_order_number ON `order`');
        $this->addSql('DROP INDEX uniq_product_slug ON product');
        $this->addSql('DROP INDEX uniq_user_email ON `user`');
    }
}
