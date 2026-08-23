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
    public function getOverallPoints(): float { return (float) ($this->payload['overallPoints'] ?? 0.0); }
    public function isBetterThan(?self $other): bool
    {
        if ($other === null || $this->getOverallPoints() > $other->getOverallPoints()) {
            return true;
        }
        if ($this->getOverallPoints() < $other->getOverallPoints()) {
            return false;
        }

        return $this->overallRank > 0
            && ($other->overallRank === 0 || $this->overallRank < $other->overallRank);
    }
    public function getScoreColor(): string
    {
        return match (true) {
            $this->getOverallPoints() < 25 => '#666666',
            $this->getOverallPoints() < 50 => '#1eff00',
            $this->getOverallPoints() < 75 => '#0070ff',
            $this->getOverallPoints() < 90 => '#a335ee',
            $this->getOverallPoints() < 99 => '#ff3c00',
            $this->getOverallPoints() < 100 => '#e268a8',
            default => '#e5cc80',
        };
    }
    public function getSpecName(?string $className): string
    {
        $names = [
            'Warrior' => ['1' => 'Arms', '2' => 'Fury', '3' => 'Protection'],
            'Paladin' => ['1' => 'Holy', '2' => 'Protection', '3' => 'Retribution'],
            'Hunter' => ['1' => 'Beast Mastery', '2' => 'Marksmanship', '3' => 'Survival'],
            'Rogue' => ['1' => 'Assassination', '2' => 'Combat', '3' => 'Subtlety'],
            'Priest' => ['1' => 'Discipline', '2' => 'Holy', '3' => 'Shadow'],
            'Death Knight' => ['1' => 'Blood', '2' => 'Frost', '3' => 'Unholy'],
            'Shaman' => ['1' => 'Elemental', '2' => 'Enhancement', '3' => 'Restoration'],
            'Mage' => ['1' => 'Arcane', '2' => 'Fire', '3' => 'Frost'],
            'Warlock' => ['1' => 'Affliction', '2' => 'Demonology', '3' => 'Destruction'],
            'Druid' => ['1' => 'Balance', '2' => 'Feral Combat', '3' => 'Restoration'],
        ];

        return $names[$className ?? ''][$this->spec ?? ''] ?? 'Unknown specialization';
    }
    public function getScrapedAt(): ?\DateTimeImmutable { return $this->scrapedAt; }
    public function setScrapedAt(\DateTimeImmutable $scrapedAt): static { $this->scrapedAt = $scrapedAt; return $this; }
}
