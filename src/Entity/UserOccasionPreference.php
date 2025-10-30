<?php
namespace App\Entity;

use App\Enum\Occasion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_occasion_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_occasion', columns: ['user_id','occasion'])]
#[ORM\Index(name: 'idx_uop_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_uop_occasion', columns: ['occasion'])]
class UserOccasionPreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    #[ORM\Column(enumType: Occasion::class)]
    private Occasion $occasion;

    #[ORM\Column(options: ['default' => 3])]
    #[Assert\Range(min: 0, max: 5)]
    private int $weight = 3; // 0..5

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(?UserProfile $user): self { $this->user = $user; return $this; }

    public function getOccasion(): Occasion { return $this->occasion; }
    public function setOccasion(Occasion $occasion): self { $this->occasion = $occasion; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }
}
