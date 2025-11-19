<?php

namespace App\Entity;

use App\Repository\AccordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccordRepository::class)]
class Accord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ex: GOURMAND, AMBERY, WOODY (unique pour faciliter les refs)
    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    // ex: "Gourmand", "Ambery", "Woody"
    #[ORM\Column(length: 100)]
    private ?string $label = null;

    // optionnel : description courte pour back-office / API
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToMany(targetEntity: Perfume::class, mappedBy: 'accords')]
    private Collection $perfumes;

    public function __construct()
    {
        $this->perfumes = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->label ?? ($this->code ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Perfume>
     */
    public function getPerfumes(): Collection
    {
        return $this->perfumes;
    }

    public function addPerfume(Perfume $perfume): self
    {
        if (!$this->perfumes->contains($perfume)) {
            $this->perfumes->add($perfume);
            $perfume->addAccord($this);
        }

        return $this;
    }

    public function removePerfume(Perfume $perfume): self
    {
        if ($this->perfumes->removeElement($perfume)) {
            $perfume->removeAccord($this);
        }

        return $this;
    }
}
