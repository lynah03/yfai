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
