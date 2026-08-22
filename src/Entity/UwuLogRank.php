<?php

namespace App\Entity;

use App\Repository\UwuLogRankRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UwuLogRankRepository::class)]
#[ORM\Table(name: 'uwu_log_ranks')]
#[ORM\UniqueConstraint(name: 'UNIQ_UWU_CHARACTER_SPEC', columns: ['name', 'realm', 'spec'])]
class UwuLogRank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column(length: 1)]
    private ?string $spec = null;

    #[ORM\Column]
    private int $overallRank = 0;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $scrapedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = ucfirst(trim($name)); return $this; }
    public function getRealm(): ?string { return $this->realm; }
    public function setRealm(string $realm): static { $this->realm = ucfirst(trim($realm)); return $this; }
    public function getSpec(): ?string { return $this->spec; }
    public function setSpec(string $spec): static { $this->spec = $spec; return $this; }
    public function getOverallRank(): int { return $this->overallRank; }
    public function setOverallRank(int $overallRank): static { $this->overallRank = max(0, $overallRank); return $this; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $payload): static { $this->payload = $payload; return $this; }
    public function getScrapedAt(): ?\DateTimeImmutable { return $this->scrapedAt; }
    public function setScrapedAt(\DateTimeImmutable $scrapedAt): static { $this->scrapedAt = $scrapedAt; return $this; }
}
