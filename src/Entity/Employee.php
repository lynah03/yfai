<?php

namespace App\Entity;

//use App\Config\Enum\Hr\EmployeeJob; No roles herarchy here
use App\Repository\EmployeeRepository;
use DateTimeInterface;
use Deprecated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
class Employee implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['employee:read','order:read','shop:read','employee_notification:read', 'employee_notification:write',
        'order:api','order:details','account:api','accountMvt:list','accountMvt:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['employee:read','order:read','shop:read'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['employee:read','order:read'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: false)]
    private ?string $password = null;
    /* Not persistant*/
    private ?string $plainPassword = null;

    #[ORM\Column]
    #[Groups(['employee:read','order:read'])]
    private ?bool $isActive = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read','order:read','shop:read','employee_notification:read', 'employee_notification:write', 'order:api'])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[Groups(['employee:read','order:read','shop:read','employee_notification:read', 'employee_notification:write', 'order:api'])]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dob = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['employee:read','employee_notification:read', 'employee_notification:write'])]
    private ?string $picture = null;
    

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['employee:read','order:read'])]
    private ?string $language = null;
    

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $apiToken = null;
    

    #[ORM\Column(type: 'uuid')]
    #[Groups(['employee:read','order:read','account:api','accountMvt:list','accountMvt:details'])]
    private ?Uuid $uuid = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['employee:read','order:read'])]
    private ?string $phone = null;
    
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class)]
    private Collection $refreshTokens;
    

    #[ORM\Column]
    private ?\DateTimeImmutable $lastLoginAt = null;

   // /**
    //     * @var Collection<int, RefreshToken>
    //     */
    //    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class)]
    //    private Collection $refreshTokens;
    //
    public function __construct()
    {
  
        $this->refreshTokens = new ArrayCollection();

    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[Deprecated(
        'The "eraseCredentials" method is deprecated since Symfony 7.3. It is no longer needed to clear sensitive data after authentication. Use the "PasswordAuthenticatedUserInterface" instead.',
        since: '7.3'
    )]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
         $this->plainPassword = null;
    }

    /**
     * @return string|null
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * @param string|null $plainPassword
     */
    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastname;
    }

    public function setLastName(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }
    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): static
    {
        $this->picture = $picture;

        return $this;
    }
	public function __toString(): string
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       	{
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       		return $this->getFirstname().' '.$this->getLastName();
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       	}


    #[Groups(['stock_mvt:details','stock_mvt:list','exp-internal:list', 'exp-internal:detail','order:details',
        'account:api','accountMvt:list','accountMvt:details','outgoingPayment:list','outgoingPayment:details'])]
	public function getFullname():string
    {
        return $this->getFirstname().' '.$this->getLastname();
    }

    
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

   
   

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(?string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

   
    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }
    
    public function __serialize(): array
    {
        // if you have any sensitive props (e.g. plainPassword), drop them here:
        // unset($this->plainPassword);

        return [
            'id'       => $this->id,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
        ];
    }


    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }
    
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }
    
    public function setRefreshTokens(Collection $refreshTokens): void
    {
        $this->refreshTokens = $refreshTokens;
    }
    
}
