<?php

namespace App\Entity;

use App\Repository\UserAccordPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAccordPreferenceRepository::class)]
#[ORM\Table(name: 'user_accord_preference')]
#[ORM\UniqueConstraint(
    name: 'uniq_user_accord_pref',
    columns: ['user_profile_id', 'accord_id']
)]
class UserAccordPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class, inversedBy: 'accordPreferences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $userProfile = null;

    #[ORM\ManyToOne(targetEntity: Accord::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Accord $accord = null;

    /**
     * Poids / appétence :
     * ex: -1 = rejette, 0 = neutre, 1 = aime bien, 2 = aime beaucoup, 3 = signature
     * (à adapter à ton modèle existant, mais même idée que les autres *Preference)
     */
    #[ORM\Column(type: 'smallint')]
    private int $weight = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserProfile(): ?UserProfile
    {
        return $this->userProfile;
    }

    public function setUserProfile(?UserProfile $userProfile): self
    {
        $this->userProfile = $userProfile;
        return $this;
    }

    public function getAccord(): ?Accord
    {
        return $this->accord;
    }

    public function setAccord(?Accord $accord): self
    {
        $this->accord = $accord;
        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;
        return $this;
    }
}
