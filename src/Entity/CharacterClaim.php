<?php

namespace App\Entity;

use App\Repository\CharacterClaimRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterClaimRepository::class)]
#[ORM\Table(name: 'character_claims', uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_CHARACTER_CLAIM_NAME_REALM', columns: ['name', 'realm'])])]
class CharacterClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'characterClaims')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = ucfirst($name);

        return $this;
    }

    public function getRealm(): ?string
    {
        return $this->realm;
    }

    public function setRealm(string $realm): static
    {
        $this->realm = ucfirst($realm);

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }
}
