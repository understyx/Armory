<?php

namespace App\Tests\Repository;

use App\Entity\CharacterComment;
use App\Entity\User;
use App\Repository\CharacterCommentRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class CharacterCommentRepositorySqliteTest extends TestCase
{
    public function testItPaginatesNewestFirstAndHidesSoftDeletedComments(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $entityManager = new EntityManager($connection, $config);
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(User::class),
            $entityManager->getClassMetadata(CharacterComment::class),
        ]);
        $user = (new User())->setDiscordId('comment-author')->setDiscordUsername('CommentAuthor');
        $entityManager->persist($user);
        $comments = [];
        for ($index = 0; $index < 22; ++$index) {
            $comment = new CharacterComment($user, 'Testchar', 'Icecrown', 'Comment '.$index);
            $createdAt = new \ReflectionProperty($comment, 'createdAt');
            $createdAt->setValue($comment, new \DateTimeImmutable(sprintf('2026-01-01 00:%02d:00', $index)));
            $entityManager->persist($comment);
            $comments[] = $comment;
        }
        $comments[21]->delete(new \DateTimeImmutable('2026-02-01'));
        $entityManager->flush();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $repository = new CharacterCommentRepository($registry);
        $page = $repository->findPage('testchar', 'icecrown', 1);

        self::assertCount(20, $page['comments']);
        self::assertTrue($page['hasMore']);
        self::assertSame('Comment 20', $page['comments'][0]->getBody());
        self::assertNotSame('Comment 21', $page['comments'][0]->getBody());
        $secondPage = $repository->findPage('testchar', 'icecrown', 2);
        self::assertCount(1, $secondPage['comments']);
        self::assertFalse($secondPage['hasMore']);

        $connection->close();
    }
}
