<?php

namespace App\Entity;

use App\Entity\Brand;
use App\Entity\PerfumeNote;
use App\Entity\Accord;
use App\Enum\Concentration;
use App\Enum\MarketingGender;
use App\Enum\Season;
use App\Enum\Occasion;
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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
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

    #[ORM\Column(nullable: true, enumType: Concentration::class)]
    private ?Concentration $concentration = Concentration::EDC;

    #[ORM\Column(nullable: true, enumType: MarketingGender::class)]
    private ?MarketingGender $marketingGender = null;

    /**
     * Seasons where this perfume performs especially well.
     *
     * Stored as enum string values: SPRING, SUMMER, FALL, WINTER.
     *
     * @var array<int,string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $seasons = null;

    /**
     * Occasions where this perfume feels most relevant.
     *
     * Stored as enum string values: CASUAL, WORK, DATE, EVENING, FORMAL, SPORT.
     *
     * @var array<int,string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $occasions = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $shortDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Assert\Url]
    private ?string $productUrl = null;

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

    /**
     * Accords olfactifs principaux (gourmand, ambré, boisé, etc.)
     *
     * Relation ManyToMany simple avec table de jointure dédiée.
     */
    #[ORM\ManyToMany(targetEntity: Accord::class, inversedBy: 'perfumes')]
    #[ORM\JoinTable(name: 'perfume_accord')]
    private Collection $accords;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->perfumeNotes = new ArrayCollection();
        $this->accords = new ArrayCollection();
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
        return ($this->brand?->getName() ? $this->brand->getName().' ' : '')
            . ($this->name ?? ('Perfume#'.$this->id));
    }

    // --- Getters/Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getReleaseYear(): ?int
    {
        return $this->releaseYear;
    }

    public function setReleaseYear(?int $y): self
    {
        $this->releaseYear = $y;

        return $this;
    }

    public function getConcentration(): ?Concentration
    {
        return $this->concentration;
    }

    public function setConcentration(?Concentration $c): self
    {
        $this->concentration = $c;

        return $this;
    }

    public function getMarketingGender(): ?MarketingGender
    {
        return $this->marketingGender;
    }

    public function setMarketingGender(?MarketingGender $g): self
    {
        $this->marketingGender = $g;

        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function getSeasons(): array
    {
        return $this->seasons ?? [];
    }

    /**
     * @param array<int,string|Season>|null $seasons
     */
    public function setSeasons(?array $seasons): self
    {
        $this->seasons = $this->normalizeSeasonValues($seasons);

        return $this;
    }

    public function addSeason(Season|string $season): self
    {
        $values = $this->getSeasons();
        $value = $season instanceof Season ? $season->value : (Season::parse($season)?->value ?? strtoupper(trim($season)));

        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }

        $this->seasons = $values;

        return $this;
    }

    public function removeSeason(Season|string $season): self
    {
        $value = $season instanceof Season ? $season->value : (Season::parse($season)?->value ?? strtoupper(trim($season)));

        $this->seasons = array_values(array_filter(
            $this->getSeasons(),
            static fn(string $item): bool => $item !== $value
        ));

        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function getOccasions(): array
    {
        return $this->occasions ?? [];
    }

    /**
     * @param array<int,string|Occasion>|null $occasions
     */
    public function setOccasions(?array $occasions): self
    {
        $this->occasions = $this->normalizeOccasionValues($occasions);

        return $this;
    }

    public function addOccasion(Occasion|string $occasion): self
    {
        $values = $this->getOccasions();
        $value = $occasion instanceof Occasion ? $occasion->value : (Occasion::parse($occasion)?->value ?? strtoupper(trim($occasion)));

        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }

        $this->occasions = $values;

        return $this;
    }

    public function removeOccasion(Occasion|string $occasion): self
    {
        $value = $occasion instanceof Occasion ? $occasion->value : (Occasion::parse($occasion)?->value ?? strtoupper(trim($occasion)));

        $this->occasions = array_values(array_filter(
            $this->getOccasions(),
            static fn(string $item): bool => $item !== $value
        ));

        return $this;
    }

    /**
     * @param array<int,string|Season>|null $seasons
     * @return array<int,string>
     */
    private function normalizeSeasonValues(?array $seasons): array
    {
        if (!$seasons) {
            return [];
        }

        $values = [];

        foreach ($seasons as $season) {
            if ($season instanceof Season) {
                $values[] = $season->value;
                continue;
            }

            if (!is_string($season) || trim($season) === '') {
                continue;
            }

            $enum = Season::parse($season);

            if ($enum instanceof Season) {
                $values[] = $enum->value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<int,string|Occasion>|null $occasions
     * @return array<int,string>
     */
    private function normalizeOccasionValues(?array $occasions): array
    {
        if (!$occasions) {
            return [];
        }

        $values = [];

        foreach ($occasions as $occasion) {
            if ($occasion instanceof Occasion) {
                $values[] = $occasion->value;
                continue;
            }

            if (!is_string($occasion) || trim($occasion) === '') {
                continue;
            }

            $enum = Occasion::parse($occasion);

            if ($enum instanceof Occasion) {
                $values[] = $enum->value;
            }
        }

        return array_values(array_unique($values));
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $d): self
    {
        $this->description = $d !== null ? trim($d) : null;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription !== null ? trim($shortDescription) : null;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image !== null ? trim($image) : null;

        return $this;
    }

    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    public function setProductUrl(?string $productUrl): self
    {
        $this->productUrl = $productUrl !== null ? trim($productUrl) : null;

        return $this;
    }

    public function getListPriceCents(): ?int
    {
        return $this->listPriceCents;
    }

    public function setListPriceCents(?int $cents): self
    {
        if ($cents !== null && $cents < 0) {
            throw new \InvalidArgumentException('listPriceCents must be >= 0 or null.');
        }

        $this->listPriceCents = $cents;

        return $this;
    }

    public function getListPriceCurrency(): string
    {
        return $this->listPriceCurrency;
    }

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
    public function getPerfumeNotes(): Collection
    {
        return $this->perfumeNotes;
    }

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

    /**
     * @return Collection<int, Accord>
     */
    public function getAccords(): Collection
    {
        return $this->accords;
    }

    public function addAccord(Accord $accord): self
    {
        if (!$this->accords->contains($accord)) {
            $this->accords->add($accord);
        }

        return $this;
    }

    public function removeAccord(Accord $accord): self
    {
        $this->accords->removeElement($accord);

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
}