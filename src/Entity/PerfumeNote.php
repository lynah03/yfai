<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'perfume_note')]
#[ORM\UniqueConstraint(name: 'uniq_perfume_note_layer', columns: ['perfume_id','note_id','layer'])]
#[ORM\Index(name: 'idx_pn_perfume', columns: ['perfume_id'])]
#[ORM\Index(name: 'idx_pn_note', columns: ['note_id'])]
class PerfumeNote
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Perfume::class, inversedBy: 'perfumeNotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Perfume $perfume = null;

    #[ORM\ManyToOne(targetEntity: Note::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Note $note = null;

    #[Assert\Choice(choices: ['TOP','HEART','BASE'])]
    #[ORM\Column(length: 10)]
    private string $layer; // 'TOP' | 'HEART' | 'BASE'

    #[Assert\Range(min: 0, max: 5)]
    #[ORM\Column(options: ['default' => 3])]
    private int $intensity = 3; // stocké mais ignoré par le scoring

    public function getId(): ?int { return $this->id; }

    public function getPerfume(): ?Perfume { return $this->perfume; }
    public function setPerfume(?Perfume $perfume): self { $this->perfume = $perfume; return $this; }

    public function getNote(): ?Note { return $this->note; }
    public function setNote(?Note $note): self { $this->note = $note; return $this; }

    public function getLayer(): string { return $this->layer; }
    public function setLayer(string $layer): self { $this->layer = strtoupper($layer); return $this; }

    public function getIntensity(): int { return $this->intensity; }
    public function setIntensity(int $intensity): self { $this->intensity = $intensity; return $this; }
}
