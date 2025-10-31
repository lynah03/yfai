<?php
    
    namespace App\Repository;
    

    use App\Entity\UserProfile;
    use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
    use Doctrine\Persistence\ManagerRegistry;
    use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
    use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
    use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
    
    /**
     * @extends ServiceEntityRepository<UserProfile>
     *
     * @implements PasswordUpgraderInterface<UserProfile>
     *
     * @method UserProfile|null find($id, $lockMode = null, $lockVersion = null)
     * @method UserProfile|null findOneBy(array $criteria, array $orderBy = null)
     * @method UserProfile[]    findAll()
     * @method UserProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
     */
    class UserProfileRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
    {
        public function __construct(ManagerRegistry $registry)
        {
            parent::__construct($registry, UserProfile::class);
        }
        
        /**
         * Used to upgrade (rehash) the user's password automatically over time.
         */
        public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
        {
            if (!$user instanceof UserProfile) {
                throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
            }
            
            $user->setPassword($newHashedPassword);
            $this->getEntityManager()->persist($user);
            $this->getEntityManager()->flush();
        }
        /* Deprecated method,
         * public function getByJob($jobId){
            return $this->createQueryBuilder('e')
                ->join('e.job','j')
                ->andWhere('j.id = :jobId')
                ->setParameter('jobId', $jobId)
                ->andWhere('e.isActive = :isActive')
                ->getQuery()
                ->getResult()
                ;
        }
         */
        
        public function checkAdmin()
        {
            return $this->createQueryBuilder('e')
                ->where('e.roles LIKE :roles')
                ->setParameter('roles', '%ROLE_SUPER_ADMIN%')
                ->getQuery()
                ->getResult()
                ;
            
        }
        public function findByAnyRole(array $roles, bool $onlyActive = true, ?array $jobs = null): array
        {
            $qb = $this->createQueryBuilder('e');
            if ($onlyActive) {
                $qb->andWhere('e.isActive = :act')->setParameter('act', true);
            }
            if ($roles) {
                $or = $qb->expr()->orX();
                foreach (array_values(array_unique($roles)) as $i => $role) {
                    $or->add($qb->expr()->like('e.roles', ':r'.$i));
                    $qb->setParameter('r'.$i, '%"'.$role.'"%');
                }
                $qb->andWhere($or);
            }
            if ($jobs) {
                $qb->andWhere('e.job IN (:jobs)')->setParameter('jobs', $jobs);
            }
            return $qb->getQuery()->getResult();
        }
        
        /**
         * Find employees that have ALL of the given roles in the JSON roles array.
         * (MySQL does this with a single JSON_CONTAINS over an array; others fall back to multiple LIKEs.)
         *
         * @param string[]   $roles
         * @param bool|null  $onlyActive
         * @return UserProfile[]
         */
        public function findByAllRoles(array $roles, ?bool $onlyActive = true): array
        {
            $roles = array_values(array_unique(array_filter($roles)));
            if (!$roles) {
                return [];
            }
            
            $qb = $this->createQueryBuilder('e');
            if ($onlyActive !== null) {
                $qb->andWhere('e.isActive = :active')->setParameter('active', $onlyActive);
            }
            
            $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getName();
            if ($platform === 'mysql') {
                // candidate array must be contained in target array
                $qb->andWhere("JSON_CONTAINS(e.roles, :roles_json, '$') = 1")
                    ->setParameter('roles_json', json_encode(array_values($roles), JSON_THROW_ON_ERROR));
            } else {
                // Fallback: AND a set of LIKEs
                foreach ($roles as $i => $role) {
                    $p = "like_all_$i";
                    $qb->andWhere($qb->expr()->like('e.roles', ':' . $p))
                        ->setParameter($p, '%"'.$role.'"%');
                }
            }
            
            return $qb->getQuery()->getResult();
        }
        
        /*
         * Find employees whose job is ANY of the provided enum values (or strings).
         *
         * @param array<EmployeeJob|string> $jobs
         * @param bool|null $onlyActive
         * @return Employee[]
         * public function findByJobs(array $jobs, ?bool $onlyActive = true): array
         * {
         * $values = array_values(array_unique(array_map(
         * fn ($j) => $j instanceof EmployeeJob ? $j->value : (string) $j,
         * array_filter($jobs)
         * )));
         * if (!$values) {
         * return [];
         * }
         *
         * $qb = $this->createQueryBuilder('e')
         * ->andWhere('e.job IN (:jobs)')
         * ->setParameter('jobs', $values);
         *
         * if ($onlyActive !== null) {
         * $qb->andWhere('e.isActive = :active')->setParameter('active', $onlyActive);
         * }
         *
         * return $qb->getQuery()->getResult();
         * }
         */
      
        
        /*
         * Combined helper: employees who match ANY of the given roles AND ANY of the given jobs.
         * Pass an empty array for either filter to ignore it.
         *
         * @param string[] $roles
         * @param array<EmployeeJob|string> $jobs
         * @param bool|null $onlyActive
         * @return Employee[]
         *  public function findByRolesAndJobs(array $roles = [], array $jobs = [], ?bool $onlyActive = true): array
        {
            // If only jobs were provided, delegate for clarity
            if ($roles && !$jobs) {
                return $this->findByAnyRole($roles, $onlyActive);
            }
            if ($jobs && !$roles) {
                return $this->findByJobs($jobs, $onlyActive);
            }
            if (!$roles && !$jobs) {
                return [];
            }
            
            // Build roles predicate first
            $qb = $this->createQueryBuilder('e');
            if ($onlyActive !== null) {
                $qb->andWhere('e.isActive = :active')->setParameter('active', $onlyActive);
            }
            
            $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getName();
            $roles = array_values(array_unique(array_filter($roles)));
            if ($platform === 'mysql') {
                $ors = [];
                foreach ($roles as $i => $role) {
                    $param = "role_$i";
                    $ors[] = "JSON_CONTAINS(e.roles, :$param, '$') = 1";
                    $qb->setParameter($param, json_encode($role));
                }
                $qb->andWhere(implode(' OR ', $ors));
            } else {
                $orX = $qb->expr()->orX();
                foreach ($roles as $i => $role) {
                    $p = "like_$i";
                    $orX->add($qb->expr()->like('e.roles', ':' . $p));
                    $qb->setParameter($p, '%"'.$role.'"%');
                }
                $qb->andWhere($orX);
            }
            
            // Now add jobs predicate
            $jobValues = array_values(array_unique(array_map(
                fn ($j) => $j instanceof EmployeeJob ? $j->value : (string) $j,
                array_filter($jobs)
            )));
            if ($jobValues) {
                $qb->andWhere('e.job IN (:jobs)')->setParameter('jobs', $jobValues);
            }
            
            return $qb->getQuery()->getResult();
        }
         */
       
        
        
    }
