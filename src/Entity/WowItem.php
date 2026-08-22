<?php

namespace App\Entity;

use App\Repository\WowItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WowItemRepository::class)]
#[ORM\Table(name: 'wow_items')]
class WowItem
{
    #[ORM\Id]
    #[ORM\Column(name: 'item_id', type: 'bigint')]
    private ?int $itemId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'item_level', type: 'integer', nullable: true)]
    private ?int $itemLevel = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quality = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $type = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $requires = null;

    #[ORM\Column(name: 'class', type: 'integer', nullable: true)]
    private ?int $class = null;

    #[ORM\Column(name: 'subclass', type: 'integer', nullable: true)]
    private ?int $subclass = null;

    #[ORM\Column(name: 'gem_slots', type: 'integer', nullable: true)]
    private ?int $gemSlots = null;

    #[ORM\Column(name: 'gear_score', type: 'integer', nullable: true)]
    private ?int $gearScore = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'tooltip_data', type: 'json', nullable: true)]
    private ?array $tooltipData = null;

    #[ORM\Column(name: 'source_build', type: 'integer', nullable: true)]
    private ?int $sourceBuild = null;

    #[ORM\Column(name: 'gear_score_source', length: 32, nullable: true)]
    private ?string $gearScoreSource = null;

    #[ORM\Column(name: 'gear_score_version', length: 32, nullable: true)]
    private ?string $gearScoreVersion = null;

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getItemLevel(): ?int
    {
        return $this->itemLevel;
    }

    public function setItemLevel(?int $itemLevel): static
    {
        $this->itemLevel = $itemLevel;

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(?int $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getRequires(): ?int
    {
        return $this->requires;
    }

    public function setRequires(?int $requires): static
    {
        $this->requires = $requires;

        return $this;
    }

    public function getClass(): ?int
    {
        return $this->class;
    }

    public function setClass(?int $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function getSubclass(): ?int
    {
        return $this->subclass;
    }

    public function setSubclass(?int $subclass): static
    {
        $this->subclass = $subclass;

        return $this;
    }

    public function getGemSlots(): ?int
    {
        return $this->gemSlots;
    }

    public function setGemSlots(?int $gemSlots): static
    {
        $this->gemSlots = $gemSlots;

        return $this;
    }

    public function getGearScore(): ?int
    {
        return $this->gearScore;
    }

    public function setGearScore(?int $gearScore): static
    {
        $this->gearScore = $gearScore;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getTooltipData(): ?array
    {
        return $this->tooltipData;
    }

    public function setTooltipData(?array $tooltipData): static
    {
        $this->tooltipData = $tooltipData;

        return $this;
    }

    public function getSourceBuild(): ?int
    {
        return $this->sourceBuild;
    }

    public function setSourceBuild(?int $sourceBuild): static
    {
        $this->sourceBuild = $sourceBuild;

        return $this;
    }

    public function getGearScoreSource(): ?string
    {
        return $this->gearScoreSource;
    }

    public function setGearScoreSource(?string $gearScoreSource): static
    {
        $this->gearScoreSource = $gearScoreSource;

        return $this;
    }

    public function getGearScoreVersion(): ?string
    {
        return $this->gearScoreVersion;
    }

    public function setGearScoreVersion(?string $gearScoreVersion): static
    {
        $this->gearScoreVersion = $gearScoreVersion;

        return $this;
    }
}
