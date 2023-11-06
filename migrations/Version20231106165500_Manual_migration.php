<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20231106165500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correct definition of table weather_data column date data type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_data  MODIFY date DATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_data MODIFY date DATETIME');
    }
}
