<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['discordId'], message: 'This Discord account is already linked to another user.', errorPath: 'discordId')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordAvatarHash = null;

    /** @var Collection<int, CharacterClaim> */
    #[ORM\OneToMany(targetEntity: CharacterClaim::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $characterClaims;

    #[ORM\OneToOne(targetEntity: CharacterClaimChallenge::class, mappedBy: 'user', orphanRemoval: true)]
    private ?CharacterClaimChallenge $claimChallenge = null;

    public function __construct()
    {
        $this->characterClaims = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->discordId;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(?string $discordId): static
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function setDiscordUsername(?string $discordUsername): static
    {
        $this->discordUsername = $discordUsername;

        return $this;
    }

    public function getDiscordAvatarHash(): ?string
    {
        return $this->discordAvatarHash;
    }

    public function setDiscordAvatarHash(?string $discordAvatarHash): static
    {
        $this->discordAvatarHash = $discordAvatarHash;

        return $this;
    }

    /** @return Collection<int, CharacterClaim> */
    public function getCharacterClaims(): Collection
    {
        return $this->characterClaims;
    }

    public function addCharacterClaim(CharacterClaim $claim): static
    {
        if (!$this->characterClaims->contains($claim)) {
            $this->characterClaims->add($claim);
            $claim->setUser($this);
        }

        return $this;
    }

    public function removeCharacterClaim(CharacterClaim $claim): static
    {
        $this->characterClaims->removeElement($claim);

        return $this;
    }

    public function getClaimChallenge(): ?CharacterClaimChallenge
    {
        return $this->claimChallenge;
    }

    public function setClaimChallenge(?CharacterClaimChallenge $claimChallenge): static
    {
        $this->claimChallenge = $claimChallenge;

        return $this;
    }
}
