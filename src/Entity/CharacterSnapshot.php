<?php

namespace App\Entity;

use App\Repository\CharacterSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterSnapshotRepository::class)]
#[ORM\Table(name: 'character_snapshots')]
#[ORM\UniqueConstraint(name: 'UNIQ_CHAR_REALM', columns: ['name', 'realm'])]
class CharacterSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column]
    private ?int $level = null;

    #[ORM\Column(length: 64)]
    private ?string $race = null;

    #[ORM\Column(length: 64)]
    private ?string $class = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $guild = null;

    #[ORM\Column]
    private ?int $gearScore = null;

    #[ORM\Column]
    private ?float $avgIlvl = null;

    #[ORM\Column(type: 'json')]
    private array $professions = [];

    #[ORM\Column(type: 'json')]
    private array $specializations = [];

    #[ORM\Column(type: 'json')]
    private array $equippedItems = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $characterModel = null;

    #[ORM\Column(type: 'json')]
    private array $talentStrings = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $talentTreesData = null;

    #[ORM\Column(type: 'json')]
    private array $glyphs = [];

    #[ORM\Column(type: 'text')]
    private ?string $enchantsStatus = null;

    #[ORM\Column(type: 'text')]
    private ?string $gemsStatus = null;

    #[ORM\Column(type: 'json')]
    private array $killStats = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pvpStats = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $matchHistory = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $scrapedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getRace(): ?string
    {
        return $this->race;
    }

    public function setRace(string $race): static
    {
        $this->race = $race;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function getGuild(): ?string
    {
        return $this->guild;
    }

    public function setGuild(?string $guild): static
    {
        $this->guild = $guild;

        return $this;
    }

    public function getGearScore(): ?int
    {
        return $this->gearScore;
    }

    public function setGearScore(int $gearScore): static
    {
        $this->gearScore = $gearScore;

        return $this;
    }

    public function getAvgIlvl(): ?float
    {
        return $this->avgIlvl;
    }

    public function setAvgIlvl(float $avgIlvl): static
    {
        $this->avgIlvl = $avgIlvl;

        return $this;
    }

    public function getProfessions(): array
    {
        return $this->professions;
    }

    public function setProfessions(array $professions): static
    {
        $this->professions = $professions;

        return $this;
    }

    public function getSpecializations(): array
    {
        return $this->specializations;
    }

    public function setSpecializations(array $specializations): static
    {
        $this->specializations = $specializations;

        return $this;
    }

    public function getEquippedItems(): array
    {
        return $this->equippedItems;
    }

    public function setEquippedItems(array $equippedItems): static
    {
        $this->equippedItems = $equippedItems;

        return $this;
    }

    public function getCharacterModel(): ?array
    {
        return $this->characterModel;
    }

    public function getGender(): ?string
    {
        return match ($this->characterModel['gender'] ?? null) {
            0, '0' => 'Male',
            1, '1' => 'Female',
            default => null,
        };
    }

    public function setCharacterModel(?array $characterModel): static
    {
        $this->characterModel = $characterModel;

        return $this;
    }

    public function getTalentStrings(): array
    {
        return $this->talentStrings;
    }

    public function setTalentStrings(array $talentStrings): static
    {
        $this->talentStrings = $talentStrings;

        return $this;
    }

    public function getTalentTreesData(): ?array
    {
        return $this->talentTreesData;
    }

    public function setTalentTreesData(?array $talentTreesData): static
    {
        $this->talentTreesData = $talentTreesData;

        return $this;
    }

    public function getGlyphs(): array
    {
        return $this->glyphs;
    }

    public function setGlyphs(array $glyphs): static
    {
        $this->glyphs = $glyphs;

        return $this;
    }

    public function getEnchantsStatus(): ?string
    {
        return $this->enchantsStatus;
    }

    public function setEnchantsStatus(?string $enchantsStatus): static
    {
        $this->enchantsStatus = $enchantsStatus;

        return $this;
    }

    public function getGemsStatus(): ?string
    {
        return $this->gemsStatus;
    }

    public function setGemsStatus(?string $gemsStatus): static
    {
        $this->gemsStatus = $gemsStatus;

        return $this;
    }

    public function getKillStats(): array
    {
        return $this->killStats;
    }

    public function setKillStats(array $killStats): static
    {
        $this->killStats = $killStats;

        return $this;
    }

    public function getPvpStats(): ?array
    {
        return $this->pvpStats;
    }

    public function setPvpStats(?array $pvpStats): static
    {
        $this->pvpStats = $pvpStats;

        return $this;
    }

    public function getMatchHistory(): ?array
    {
        return $this->matchHistory;
    }

    public function setMatchHistory(?array $matchHistory): static
    {
        $this->matchHistory = $matchHistory;

        return $this;
    }

    public function getScrapedAt(): ?\DateTimeImmutable
    {
        return $this->scrapedAt;
    }

    public function setScrapedAt(\DateTimeImmutable $scrapedAt): static
    {
        $this->scrapedAt = $scrapedAt;

        return $this;
    }
}
