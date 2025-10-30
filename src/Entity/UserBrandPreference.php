<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_brand_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_brand', columns: ['user_id','brand_id'])]
#[ORM\Index(name: 'idx_ubp_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_ubp_brand', columns: ['brand_id'])]
class UserBrandPreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Brand $brand = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: -5, max: 5)]
    private int $weight = 0; // −5..+5

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(?UserProfile $user): self { $this->user = $user; return $this; }

    public function getBrand(): ?Brand { return $this->brand; }
    public function setBrand(?Brand $brand): self { $this->brand = $brand; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }
}

