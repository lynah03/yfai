<?php
namespace App\Entity;

use App\Entity\Brand;
use App\Entity\PerfumeNote;

use App\Enum\Concentration;
use App\Enum\MarketingGender;
use App\Repository\PerfumeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PerfumeRepository::class)]
#[ORM\Table(name: 'perfume')]
#[ORM\UniqueConstraint(name: 'uniq_perfume_brand_name', columns: ['brand_id','name'])]
#[ORM\Index(name: 'idx_perfume_brand', columns: ['brand_id'])]
#[ORM\Index(name: 'idx_perfume_price', columns: ['list_price_cents'])]
#[ORM\HasLifecycleCallbacks]
class Perfume
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'perfumes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Brand $brand = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    private ?int $releaseYear = null;

    // Stocké en string (on garde la souplesse côté import + validation stricte)
    #[ORM\Column(nullable: true, enumType: Concentration::class)]
    private ?Concentration $concentration = Concentration::EDC; // EDC/EDT/EDP/PARFUM/EXTRAIT

    // Enum forte pour la cible marketing
    #[ORM\Column(nullable: true, enumType: MarketingGender::class)]
    private ?MarketingGender $marketingGender = null; // MEN/WOMEN/UNISEX

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Prix catalogue (p.ex. RRP) en CENTIMES. Nullable si inconnu.
     * Exemple: 99.00 € => 9900
     */
    #[ORM\Column(name: 'list_price_cents', type: 'integer', nullable: true, options: ['unsigned' => true])]
    #[Assert\PositiveOrZero]
    private ?int $listPriceCents = null;

    /**
     * Devise ISO 4217 (3 lettres). Par défaut EUR.
     */
    #[ORM\Column(name: 'list_price_currency', length: 3, options: ['default' => 'EUR'])]
    #[Assert\Currency]
    private string $listPriceCurrency = 'EUR';

    /** @var Collection<int, PerfumeNote> */
    #[ORM\OneToMany(mappedBy: 'perfume', targetEntity: PerfumeNote::class, cascade: ['persist','remove'], orphanRemoval: true)]
    private Collection $perfumeNotes;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->perfumeNotes = new ArrayCollection();
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
        return ($this->brand?->getName() ? $this->brand->getName().' ' : '') . ($this->name ?? ('Perfume#'.$this->id));
    }

    // --- Getters/Setters ---

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getBrand(): ?Brand { return $this->brand; }
    public function setBrand(?Brand $brand): self { $this->brand = $brand; return $this; }

    public function getReleaseYear(): ?int { return $this->releaseYear; }
    public function setReleaseYear(?int $y): self { $this->releaseYear = $y; return $this; }

    public function getConcentration(): ?Concentration { return $this->concentration; }
    public function setConcentration(?Concentration $c): self
    {
       $this->concentration = $c;
       return $this;
    }

    public function getMarketingGender(): ?MarketingGender { return $this->marketingGender; }
    public function setMarketingGender(?MarketingGender $g): self { $this->marketingGender = $g; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getListPriceCents(): ?int { return $this->listPriceCents; }
    public function setListPriceCents(?int $cents): self
    {
        if ($cents !== null && $cents < 0) {
            throw new \InvalidArgumentException('listPriceCents must be >= 0 or null.');
        }
        $this->listPriceCents = $cents;
        return $this;
    }

    public function getListPriceCurrency(): string { return $this->listPriceCurrency; }
    public function setListPriceCurrency(string $currency): self
    {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
        $this->listPriceCurrency = $currency;
        return $this;
    }

    /** @return Collection<int, PerfumeNote> */
    public function getPerfumeNotes(): Collection { return $this->perfumeNotes; }

    public function addPerfumeNote(PerfumeNote $pn): self
    {
        if (!$this->perfumeNotes->contains($pn)) {
            $this->perfumeNotes->add($pn);
            $pn->setPerfume($this);
        }
        return $this;
    }

    public function removePerfumeNote(PerfumeNote $pn): self
    {
        if ($this->perfumeNotes->removeElement($pn) && $pn->getPerfume() === $this) {
            $pn->setPerfume(null);
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
