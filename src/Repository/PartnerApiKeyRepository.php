<?php

namespace App\Repository;

use App\Entity\Brand;
use App\Entity\PartnerApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PartnerApiKey>
 */
final class PartnerApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerApiKey::class);
    }

    /**
     * @return PartnerApiKey[]
     */
    public function findForBrand(Brand $brand): array
    {
        return $this->createQueryBuilder('k')
            ->addSelect('CASE WHEN k.revokedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN revokedSort')
            ->andWhere('k.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('revokedSort', 'ASC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByBrandAndPrefix(Brand $brand, string $prefix): ?PartnerApiKey
    {
        $prefix = trim($prefix);

        if ($prefix === '') {
            return null;
        }

        return $this->createQueryBuilder('k')
            ->andWhere('k.brand = :brand')
            ->andWhere('k.keyPrefix = :prefix')
            ->andWhere('k.revokedAt IS NULL')
            ->setParameter('brand', $brand)
            ->setParameter('prefix', $prefix)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
