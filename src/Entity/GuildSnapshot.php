<?php

namespace App\Entity;

use App\Repository\GuildSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuildSnapshotRepository::class)]
#[ORM\Table(name: 'guild_snapshots')]
#[ORM\UniqueConstraint(name: 'UNIQ_GUILD_REALM', columns: ['name', 'realm'])]
class GuildSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $faction = null;

    #[ORM\Column]
    private int $memberCount = 0;

    #[ORM\Column]
    private int $pvePoints = 0;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $members = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $scrapedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getRealm(): ?string { return $this->realm; }
    public function setRealm(string $realm): static { $this->realm = ucfirst(trim($realm)); return $this; }
    public function getFaction(): ?string { return $this->faction; }
    public function setFaction(?string $faction): static { $this->faction = $faction; return $this; }
    public function getMemberCount(): int { return $this->memberCount; }
    public function setMemberCount(int $memberCount): static { $this->memberCount = $memberCount; return $this; }
    public function getPvePoints(): int { return $this->pvePoints; }
    public function setPvePoints(int $pvePoints): static { $this->pvePoints = $pvePoints; return $this; }
    public function getMembers(): array { return $this->members; }
    public function setMembers(array $members): static { $this->members = array_values($members); return $this; }
    public function getScrapedAt(): ?\DateTimeImmutable { return $this->scrapedAt; }
    public function setScrapedAt(\DateTimeImmutable $scrapedAt): static { $this->scrapedAt = $scrapedAt; return $this; }
}
