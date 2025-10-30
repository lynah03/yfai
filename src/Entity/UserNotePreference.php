<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_note_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_note', columns: ['user_id','note_id'])]
#[ORM\Index(name: 'idx_unp_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_unp_note', columns: ['note_id'])]
class UserNotePreference
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $user = null;

    #[ORM\ManyToOne(targetEntity: Note::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Note $note = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: -5, max: 5)]
    private int $weight = 0; // −5..+5

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?UserProfile { return $this->user; }
    public function setUser(?UserProfile $user): self { $this->user = $user; return $this; }

    public function getNote(): ?Note { return $this->note; }
    public function setNote(?Note $note): self { $this->note = $note; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }
}

