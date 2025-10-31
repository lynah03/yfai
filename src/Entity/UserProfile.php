<?php
namespace App\Entity;

use App\Enum\Gender;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]#[ORM\Table(name: 'user_profile')]
#[ORM\UniqueConstraint(name: 'uniq_userprofile_name', columns: ['name'])]
#[UniqueEntity('name')]
#[ORM\HasLifecycleCallbacks]


class UserProfile implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
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
        return $this->name ?? ('User#'.$this->id);
    }

    // --- Getters/Setters ---
    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getGender(): Gender { return $this->gender; }
    public function setGender(Gender $gender): self { $this->gender = $gender; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
    
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
    
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
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
    
    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }
    
    public function setVerified(?bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }
}

