<?php

namespace App\Entity;

final readonly class GuildRankSummary
{
    public function __construct(
        private float $overallPoints,
        private int $overallRank,
        private string $spec,
    ) {
    }

    public function getOverallPoints(): float
    {
        return $this->overallPoints;
    }

    public function getOverallRank(): int
    {
        return $this->overallRank;
    }

    public function getScoreColor(): string
    {
        return match (true) {
            $this->overallPoints < 25 => '#9ea7bb',
            $this->overallPoints < 50 => '#1eff00',
            $this->overallPoints < 75 => '#0070ff',
            $this->overallPoints < 90 => '#a335ee',
            $this->overallPoints < 99 => '#ff3c00',
            $this->overallPoints < 100 => '#e268a8',
            default => '#e5cc80',
        };
    }

    public function specName(?string $className): string
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

        return $names[$className ?? ''][$this->spec] ?? 'Unknown specialization';
    }
}
