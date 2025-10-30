<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'user_budget_preference')]
#[ORM\UniqueConstraint(name: 'uniq_budget_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_budget_user', columns: ['user_id'])]
#[UniqueEntity(fields: ['user'], message: 'A budget preference already exists for this user.')]
#[ORM\HasLifecycleCallbacks]
class UserBudgetPreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    /**
     * Budget minimum en centimes (nullable si l’utilisateur ne fixe pas de minimum).
     * Exemple : 50.00 € => 5000
     */
    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[Assert\PositiveOrZero]
    private ?int $minCents = null;

    /**
     * Budget maximum en centimes (nullable si l’utilisateur ne fixe pas de maximum).
     */
    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[Assert\PositiveOrZero]
    private ?int $maxCents = null;

    /**
     * Devise ISO 4217 (3 lettres). Par défaut EUR.
     */
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Assert\Currency]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // -------- Integrity at entity level: max >= min (when both provided)
    #[Assert\Expression(
        'this.getMaxCents() === null or this.getMinCents() === null or this.getMaxCents() >= this.getMinCents()',
        message: 'maxCents must be greater than or equal to minCents.'
    )]
    public function isRangeValid(): bool
    {
        // Méthode "dummy" uniquement pour porter l’assertion au niveau entité
        return true;
    }

    // -------- Lifecycle

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

    // -------- Getters/Setters

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(UserProfile $user): self { $this->user = $user; return $this; }

    public function getMinCents(): ?int { return $this->minCents; }
    public function setMinCents(?int $min): self
    {
        if ($min !== null && $min < 0) {
            throw new \InvalidArgumentException('minCents must be >= 0 or null.');
        }
        $this->minCents = $min;
        return $this;
    }

    public function getMaxCents(): ?int { return $this->maxCents; }
    public function setMaxCents(?int $max): self
    {
        if ($max !== null && $max < 0) {
            throw new \InvalidArgumentException('maxCents must be >= 0 or null.');
        }
        $this->maxCents = $max;
        return $this;
    }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self
    {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
        $this->currency = $currency;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
