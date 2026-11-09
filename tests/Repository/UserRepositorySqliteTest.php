<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class UserRepositorySqliteTest extends TestCase
{
    public function testUsernameLookupIgnoresCaseAndChoosesMostRecentLogin(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $entityManager = new EntityManager($connection, $config);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(User::class)]);
        $old = (new User())->setDiscordId('old')->setDiscordUsername('SharedName')->setLastLoginAt(new \DateTimeImmutable('-2 days'));
        $recent = (new User())->setDiscordId('recent')->setDiscordUsername('sharedname')->setLastLoginAt(new \DateTimeImmutable('-1 day'));
        $entityManager->persist($old);
        $entityManager->persist($recent);
        $entityManager->flush();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $repository = new UserRepository($registry);

        self::assertSame('recent', $repository->findLatestByDiscordUsername('SHAREDNAME')?->getDiscordId());

        $connection->close();
    }
}
