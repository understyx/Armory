<?php

namespace App\Repository;

use App\Entity\CharacterComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CharacterComment> */
class CharacterCommentRepository extends ServiceEntityRepository
{
    public const PAGE_SIZE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterComment::class);
    }

    /** @return array{comments: list<CharacterComment>, hasMore: bool} */
    public function findPage(string $name, string $realm, int $page): array
    {
        $comments = $this->createQueryBuilder('comment')
            ->select('comment', 'author')
            ->join('comment.user', 'author')
            ->where('comment.name = :name')
            ->andWhere('comment.realm = :realm')
            ->andWhere('comment.deletedAt IS NULL')
            ->setParameter('name', ucfirst(trim($name)))
            ->setParameter('realm', ucfirst(trim($realm)))
            ->orderBy('comment.createdAt', 'DESC')
            ->addOrderBy('comment.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE + 1)
            ->getQuery()
            ->getResult();

        $hasMore = count($comments) > self::PAGE_SIZE;
        if ($hasMore) {
            array_pop($comments);
        }

        return ['comments' => $comments, 'hasMore' => $hasMore];
    }

    public function findActiveById(int $id): ?CharacterComment
    {
        return $this->findOneBy(['id' => $id, 'deletedAt' => null]);
    }
}
