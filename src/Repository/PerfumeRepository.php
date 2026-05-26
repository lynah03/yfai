<?php
namespace App\Repository;

use App\Entity\Perfume;
use App\Entity\Brand;
use App\Enum\MarketingGender;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Perfume>
 */
class PerfumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Perfume::class);
    }

    /**
     * Charge une liste de parfums avec leur marque et leurs notes (eager).
     * Passer null comme limite charge tout le catalogue.
     * Utile pour le matcher (scoring).
     *
     * @return Perfume[]
     */
    public function findAllWithBrandAndNotes(?int $limit = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('b','pn','n')
            ->join('p.brand','b')
            ->leftJoin('p.perfumeNotes','pn')
            ->leftJoin('pn.note','n')
            ->orderBy('b.name','ASC')
            ->addOrderBy('p.name','ASC');

        if ($limit !== null) {
            $qb->setFirstResult($offset)
                ->setMaxResults($limit);
        } elseif ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Load the full catalog with data needed by the matcher.
     *
     * Scalar scoring fields such as concentration, marketing gender, seasons,
     * occasions and price are loaded with the Perfume entity. Accords are
     * preloaded separately to avoid multiplying note rows by accord rows.
     *
     * @return Perfume[]
     */
    public function findAllForMatching(): array
    {
        /** @var Perfume[] $perfumes */
        $perfumes = $this->matchingBaseQueryBuilder()
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $this->preloadAccords($perfumes);

        return $perfumes;
    }

    /**
     * Load one brand catalog with data needed by the matcher.
     *
     * @return Perfume[]
     */
    public function findByBrandForMatching(Brand $brand): array
    {
        /** @var Perfume[] $perfumes */
        $perfumes = $this->matchingBaseQueryBuilder()
            ->andWhere('b = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $this->preloadAccords($perfumes);

        return $perfumes;
    }

    private function matchingBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->addSelect('b', 'pn', 'n')
            ->join('p.brand', 'b')
            ->leftJoin('p.perfumeNotes', 'pn')
            ->leftJoin('pn.note', 'n');
    }

    /**
     * @param Perfume[] $perfumes
     */
    private function preloadAccords(array $perfumes): void
    {
        $ids = array_values(array_filter(array_map(
            static fn(Perfume $perfume): ?int => $perfume->getId(),
            $perfumes
        )));

        if ($ids === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->addSelect('a')
            ->leftJoin('p.accords', 'a')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Charge un parfum avec sa marque et ses notes (eager).
     */
    public function findOneWithBrandAndNotes(int $id): ?Perfume
    {
        return $this->createQueryBuilder('p')
            ->addSelect('b','pn','n')
            ->join('p.brand','b')
            ->leftJoin('p.perfumeNotes','pn')
            ->leftJoin('pn.note','n')
            ->andWhere('p.id = :id')->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Search multi-critères + pagination simple.
     *
     * Filters possibles (tous optionnels) dans $filters:
     *  - q: string (contient dans le nom, case-insensitive)
     *  - brandId: int|int[]
     *  - brandName: string (contient, CI)
     *  - concentration: string|string[] (ex. 'EDP','PARFUM'… — stocké en UPPER)
     *  - marketingGender: MarketingGender|MarketingGender[] (MEN/WOMEN/UNISEX)
     *  - releaseYearMin: int
     *  - releaseYearMax: int
     *  - noteIdsAny: int[] (au moins une de ces notes)
     *  - noteIdsAll: int[] (contient toutes ces notes)
     *
     * @return Perfume[]
     */
    public function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $qb = $this->baseQBWithBrand($filters);

        $this->applyFilters($qb, $filters, withNotes: true);

        $qb->orderBy('b.name', 'ASC')
           ->addOrderBy('p.name', 'ASC')
           ->setFirstResult($offset)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Total correspondant à search() (pour pagination).
     */
    public function countSearch(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)');

        // Joins minimums selon filtres
        $withNotes = !empty($filters['noteIdsAny']) || !empty($filters['noteIdsAll']);
        $this->applyFilters($qb, $filters, withNotes: $withNotes);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Suggestions de noms (auto-complétion) par préfixe.
     * @return string[]
     */
    public function suggestNames(string $prefix, int $limit = 10): array
    {
        $prefix = mb_strtoupper(trim($prefix));
        if ($prefix === '') return [];

        $rows = $this->createQueryBuilder('p')
            ->select('p.name')
            ->join('p.brand','b')
            ->andWhere('UPPER(p.name) LIKE :p')->setParameter('p', $prefix.'%')
            ->orderBy('b.name','ASC')->addOrderBy('p.name','ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $r) => $r['name'], $rows);
    }

    /**
     * Parfums d’une marque (objet ou id), paginés.
     * @return Perfume[]
     */
    public function findByBrand(Brand|int $brand, int $limit = 20, int $offset = 0): array
    {
        $brandId = $brand instanceof Brand ? $brand->getId() : $brand;

        return $this->createQueryBuilder('p')
            ->addSelect('b')
            ->join('p.brand','b')
            ->andWhere('b.id = :bid')->setParameter('bid', $brandId)
            ->orderBy('p.name','ASC')
            ->setFirstResult($offset)->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Sélection aléatoire (utile pour tests/découverte).
     * @return Perfume[]
     */
    public function random(int $limit = 5): array
    {
        // Attention: RAND() est propre à MySQL; adapter si besoin (PostgreSQL: RANDOM()).
        return $this->createQueryBuilder('p')
            ->addSelect('b')
            ->join('p.brand','b')
            ->orderBy('RAND()')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    // ------------------- Helpers privés -------------------

    private function baseQBWithBrand(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('b')
            ->join('p.brand','b');

        // Si des filtres notes sont présents, on joindra les notes plus loin.
        return $qb;
    }

    /**
     * Applique les filtres de recherche de façon conditionnelle.
     */
    private function applyFilters(QueryBuilder $qb, array $filters, bool $withNotes): void
    {
        // q (name contains)
        if (!empty($filters['q'])) {
            $q = '%'.mb_strtoupper(trim((string)$filters['q'])).'%';
            $qb->andWhere('UPPER(p.name) LIKE :q')->setParameter('q', $q);
        }

        // brandId (one or many)
        if (!empty($filters['brandId'])) {
            $ids = (array) $filters['brandId'];
            $qb->andWhere('b.id IN (:bids)')->setParameter('bids', $ids);
        }

        // brandName contains
        if (!empty($filters['brandName'])) {
            $bn = '%'.mb_strtoupper(trim((string)$filters['brandName'])).'%';
            $qb->andWhere('UPPER(b.name) LIKE :bn')->setParameter('bn', $bn);
        }

        // concentration (stored as uppercase string)
        if (!empty($filters['concentration'])) {
            $vals = array_map(
                fn($v) => mb_strtoupper(trim((string)$v)),
                (array) $filters['concentration']
            );
            $qb->andWhere('p.concentration IN (:conc)')->setParameter('conc', $vals);
        }

        // marketingGender (enum)
        if (!empty($filters['marketingGender'])) {
            $mg = (array) $filters['marketingGender'];
            // Doctrine stocke l'enum comme string par défaut
            $mgVals = array_map(fn($g) => $g instanceof MarketingGender ? $g->value : (string)$g, $mg);
            $qb->andWhere('p.marketingGender IN (:mg)')->setParameter('mg', $mgVals);
        }

        // releaseYear range
        if (!empty($filters['releaseYearMin'])) {
            $qb->andWhere('p.releaseYear >= :ymin')->setParameter('ymin', (int)$filters['releaseYearMin']);
        }
        if (!empty($filters['releaseYearMax'])) {
            $qb->andWhere('p.releaseYear <= :ymax')->setParameter('ymax', (int)$filters['releaseYearMax']);
        }

        // Notes filters (ANY/ALL)
        $needsNotesJoin = $withNotes || !empty($filters['noteIdsAny']) || !empty($filters['noteIdsAll']);
        if ($needsNotesJoin) {
            // éviter les doubles joins si déjà présents
            $aliases = array_map(fn($j) => $j->getAlias(), $qb->getDQLPart('join')['p'] ?? []);
            if (!in_array('pn', $aliases ?? [], true)) {
                $qb->leftJoin('p.perfumeNotes','pn');
            }
            if (!in_array('n', $aliases ?? [], true)) {
                $qb->leftJoin('pn.note','n');
            }
        }

        // noteIdsAny: au moins une des notes
        if (!empty($filters['noteIdsAny'])) {
            $any = array_map('intval', (array)$filters['noteIdsAny']);
            $qb->andWhere('n.id IN (:anyNotes)')->setParameter('anyNotes', $any);
        }

        // noteIdsAll: contient toutes ces notes
        if (!empty($filters['noteIdsAll'])) {
            // On ajoute une sous-requête groupée : HAVING COUNT(DISTINCT n.id) = N
            $all = array_map('intval', (array)$filters['noteIdsAll']);
            // On doit regrouper par p.id pour HAVING
            $qb->groupBy('p.id');
            $qb->andHaving('COUNT(DISTINCT CASE WHEN n.id IN (:allNotes) THEN n.id END) = :allCount')
               ->setParameter('allNotes', $all)
               ->setParameter('allCount', count($all));
        }
    }
}
