<?php

namespace App\Entity;

use App\Repository\CharacterCommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterCommentRepository::class)]
#[ORM\Table(name: 'character_comments', indexes: [
    new ORM\Index(name: 'IDX_CHARACTER_COMMENT_NAME_REALM_CREATED', columns: ['name', 'realm', 'created_at']),
    new ORM\Index(name: 'IDX_CHARACTER_COMMENT_USER_CREATED', columns: ['user_id', 'created_at']),
])]
class CharacterComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column(length: 255)]
    private ?string $body = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(User $user, string $name, string $realm, string $body)
    {
        $this->user = $user;
        $this->name = ucfirst(trim($name));
        $this->realm = ucfirst(trim($realm));
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function getName(): ?string { return $this->name; }
    public function getRealm(): ?string { return $this->realm; }
    public function getBody(): ?string { return $this->body; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }

    public function delete(\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}
