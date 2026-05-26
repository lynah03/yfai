<?php
namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    /**
     * Retourne une marque par nom (trim + insensible à la casse).
     */
    public function findOneByNameCI(string $name): ?Brand
    {
        $name = trim($name);
        if ($name === '') { return null; }

        return $this->createQueryBuilder('b')
            ->where('UPPER(b.name) = :name')
            ->setParameter('name', mb_strtoupper($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByPartnerSlug(string $slug): ?Brand
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return $this->findOneBy(['partnerSlug' => $slug]);
    }

    public function findOneByPartnerSlugCI(string $slug): ?Brand
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return $this->createQueryBuilder('b')
            ->where('UPPER(b.partnerSlug) = :slug')
            ->setParameter('slug', mb_strtoupper($slug))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche par nom (LIKE insensible à la casse) avec pagination simple.
     * @return Brand[]
     */
    public function searchByName(string $q, int $limit = 20, int $offset = 0): array
    {
        $q = '%'.mb_strtoupper(trim($q)).'%';

        return $this->createQueryBuilder('b')
            ->where('UPPER(b.name) LIKE :q')
            ->setParameter('q', $q)
            ->orderBy('b.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marques avec nombre de parfums liés.
     * @return array<int, array{brand: Brand, perfumeCount: int}>
     */
    public function findAllWithPerfumeCounts(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->select('b AS brand, COUNT(p.id) AS perfumeCount')
            ->leftJoin('b.perfumes', 'p')
            ->groupBy('b.id')
            ->orderBy('perfumeCount', 'DESC')
            ->addOrderBy('b.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Suggestions d’auto-complétion par préfixe.
     * @return string[] Liste de noms
     */
    public function suggestNames(string $prefix, int $limit = 10): array
    {
        $prefix = mb_strtoupper(trim($prefix));
        if ($prefix === '') { return []; }

        $rows = $this->createQueryBuilder('b')
            ->select('b.name')
            ->where('UPPER(b.name) LIKE :p')
            ->setParameter('p', $prefix.'%')
            ->orderBy('b.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $r) => $r['name'], $rows);
    }

    /**
     * Crée ou récupère une marque par nom (utilitaire d’import).
     * Ne fait pas le flush (laisse au service appelant).
     */
    public function findOrCreateByName(string $name): Brand
    {
        $name = trim($name);
        $brand = $this->findOneByNameCI($name);
        if ($brand) { return $brand; }

        $brand = new Brand();
        $brand->setName($name);

        $em = $this->getEntityManager();
        $em->persist($brand);

        return $brand;
    }

    /**
     * Top marques par volume de parfums.
     * @return array<int, array{brand: Brand, perfumeCount: int}>
     */
    public function topByPerfumeCount(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->select('b AS brand, COUNT(p.id) AS perfumeCount')
            ->leftJoin('b.perfumes', 'p')
            ->groupBy('b.id')
            ->orderBy('perfumeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
