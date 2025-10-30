<?php
namespace App\Entity;

use App\Enum\Concentration;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_concentration_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_concentration', columns: ['user_id','concentration'])]
#[ORM\Index(name: 'idx_ucp_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_ucp_conc', columns: ['concentration'])]
class UserConcentrationPreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    #[ORM\Column(enumType: Concentration::class)]
    private Concentration $concentration;

    #[ORM\Column(options: ['default' => 3])]
    #[Assert\Range(min: 0, max: 5)]
    private int $weight = 3; // 0..5

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(?UserProfile $user): self { $this->user = $user; return $this; }

    public function getConcentration(): Concentration { return $this->concentration; }
    public function setConcentration(Concentration $concentration): self { $this->concentration = $concentration; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }
}
