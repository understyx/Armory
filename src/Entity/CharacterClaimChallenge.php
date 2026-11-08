<?php

namespace App\Entity;

use App\Repository\CharacterClaimChallengeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterClaimChallengeRepository::class)]
#[ORM\Table(name: 'character_claim_challenges')]
class CharacterClaimChallenge
{
    public const SCANNING_BASELINE = 'scanning_baseline';
    public const AWAITING_REMOVAL = 'awaiting_removal';
    public const SCANNING_VERIFY = 'scanning_verify';
    public const VERIFIED = 'verified';
    public const NOT_FOUND = 'not_found';
    public const NO_ITEMS = 'no_items';
    public const EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'claimChallenge')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 64)]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    private ?string $realm = null;

    #[ORM\Column(length: 32)]
    private string $state = self::SCANNING_BASELINE;

    /** @var list<array{slot: string, id: int, name: string, icon: ?string}> */
    #[ORM\Column(type: 'json')]
    private array $requiredSlots = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scanLeaseUntil = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $stateRevision = 0;

    public function __construct(User $user, string $name, string $realm, \DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->name = ucfirst($name);
        $this->realm = ucfirst($realm);
        $this->state = self::SCANNING_BASELINE;
        $this->createdAt = $createdAt;
        $this->expiresAt = $createdAt->modify('+1 hour');
        $this->updatedAt = $createdAt;
        $user->setClaimChallenge($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRealm(): ?string
    {
        return $this->realm;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();
        ++$this->stateRevision;

        return $this;
    }

    /** @return list<array{slot: string, id: int, name: string, icon: ?string}> */
    public function getRequiredSlots(): array
    {
        return $this->requiredSlots;
    }

    /** @param list<array{slot: string, id: int, name: string, icon: ?string}> $requiredSlots */
    public function setRequiredSlots(array $requiredSlots): static
    {
        $this->requiredSlots = array_values($requiredSlots);
        $this->updatedAt = new \DateTimeImmutable();
        ++$this->stateRevision;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getScanLeaseUntil(): ?\DateTimeImmutable
    {
        return $this->scanLeaseUntil;
    }

    public function setScanLeaseUntil(?\DateTimeImmutable $scanLeaseUntil): static
    {
        $this->scanLeaseUntil = $scanLeaseUntil;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStateRevision(): int
    {
        return $this->stateRevision;
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [self::VERIFIED, self::NOT_FOUND, self::NO_ITEMS, self::EXPIRED], true);
    }
}
