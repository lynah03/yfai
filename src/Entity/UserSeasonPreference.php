<?php
namespace App\Entity;

use App\Enum\Season;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_season_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_season', columns: ['user_id','season'])]
#[ORM\Index(name: 'idx_usp_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_usp_season', columns: ['season'])]
class UserSeasonPreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    #[ORM\Column(enumType: Season::class)]
    private Season $season;

    #[ORM\Column(options: ['default' => 3])]
    #[Assert\Range(min: 0, max: 5)]
    private int $weight = 3; // 0..5

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(?UserProfile $user): self { $this->user = $user; return $this; }

    public function getSeason(): Season { return $this->season; }
    public function setSeason(Season $season): self { $this->season = $season; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }
}

