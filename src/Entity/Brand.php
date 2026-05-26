<?php
namespace App\Entity;

use App\Repository\BrandRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BrandRepository::class)]
#[ORM\Table(name: 'brand')]
#[ORM\UniqueConstraint(name: 'uniq_brand_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_brand_partner_slug', columns: ['partner_slug'])]
#[ORM\Index(name: 'idx_brand_country', columns: ['country'])]
#[UniqueEntity('name')]
#[UniqueEntity('partnerSlug')]
#[ORM\HasLifecycleCallbacks]
class Brand
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $name;

    #[ORM\Column(name: 'partner_slug', length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $partnerSlug = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $country = null; // should be an enum to represent countries and to be translatable easily

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Perfume> */
    #[ORM\OneToMany(mappedBy: 'brand', targetEntity: Perfume::class)]
    private Collection $perfumes;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;
    

    public function __construct()
    {
        $this->perfumes = new ArrayCollection();
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
        return $this->name ?? ('Brand#'.$this->id);
    }

    // --- Getters/Setters ---

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }

    public function getPartnerSlug(): ?string { return $this->partnerSlug; }
    public function setPartnerSlug(?string $partnerSlug): self { $this->partnerSlug = $partnerSlug !== null ? trim($partnerSlug) : null; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): self { $this->country = $country; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, Perfume> */
    public function getPerfumes(): Collection { return $this->perfumes; }

    public function addPerfume(Perfume $perfume): self
    {
        if (!$this->perfumes->contains($perfume)) {
            $this->perfumes->add($perfume);
            $perfume->setBrand($this);
        }
        return $this;
    }

    public function removePerfume(Perfume $perfume): self
    {
        if ($this->perfumes->removeElement($perfume)) {
            if ($perfume->getBrand() === $this) {
                $perfume->setBrand(null);
            }
        }
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }
}
