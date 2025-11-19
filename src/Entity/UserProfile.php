<?php

namespace App\Entity;

use App\Enum\Gender;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\UserAccordPreference;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
#[ORM\Table(name: 'user_profile')]
#[ORM\UniqueConstraint(name: 'uniq_userprofile_name', columns: ['name'])]
#[UniqueEntity('name')]
#[ORM\HasLifecycleCallbacks]
class UserProfile implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ORM\Column(enumType: Gender::class)]
    private Gender $gender = Gender::UNDISCLOSED;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?bool $isVerified = null;

    #[ORM\Column]
    private array $roles = ['ROLE_CUSTOMER_USER'];

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    /**
     * Préférences d'accords olfactifs.
     *
     * @var Collection<int, UserAccordPreference>
     */
    #[ORM\OneToMany(
        mappedBy: 'userProfile',
        targetEntity: UserAccordPreference::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $accordPreferences;

    public function __construct()
    {
        $this->accordPreferences = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? ('User#' . $this->id);
    }

    // --- Getters/Setters de base ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function setGender(Gender $gender): self
    {
        $this->gender = $gender;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // --- Security / Auth ---

    public function eraseCredentials(): void
    {
        // nettoyer les données sensibles temporaires ici si besoin
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    // --- Email ---

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    // --- Vérification ---

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setVerified(?bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    // --- Accords preferences ---

    /**
     * @return Collection<int, UserAccordPreference>
     */
    public function getAccordPreferences(): Collection
    {
        return $this->accordPreferences;
    }

    public function addAccordPreference(UserAccordPreference $preference): self
    {
        if (!$this->accordPreferences->contains($preference)) {
            $this->accordPreferences->add($preference);
            $preference->setUserProfile($this);
        }

        return $this;
    }

    public function removeAccordPreference(UserAccordPreference $preference): self
    {
        if ($this->accordPreferences->removeElement($preference)) {
            if ($preference->getUserProfile() === $this) {
                $preference->setUserProfile(null);
            }
        }

        return $this;
    }
}
