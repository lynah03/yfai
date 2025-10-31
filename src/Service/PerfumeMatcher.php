<?php
namespace App\Service;

use App\Entity\Note;
use App\Entity\Perfume;
use App\Entity\PerfumeNote;
use App\Entity\UserProfile;
use App\Entity\UserNotePreference;
use App\Entity\UserBrandPreference;
use App\Entity\UserConcentrationPreference;
use App\Entity\UserBudgetPreference;
use App\Enum\Concentration as ConcEnum;
use Doctrine\ORM\EntityManagerInterface;

class PerfumeMatcher
{
    // Notes
    private const LAYER_COEF      = ['HEART' => 1.6, 'TOP' => 1.0, 'BASE' => 1.3];

    // Marque / Concentration
    private const BRAND_COEF      = 0.8;   // × weight (−5..+5)
    private const CONC_COEF       = 0.6;   // × (userConc/5)

    // Budget
    private const BUDGET_BONUS    = 0.6;   // bonus si dans la fourchette
    private const BUDGET_PENALTY  = -1.0;  // malus si hors fourchette

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Classement (offset = 0).
     * @return array<int, array{perfume: Perfume, score: float, reasons: string[]}>
     */
    public function rankForUser(UserProfile $user, int $limit = 20, int $maxReasons = 8): array
    {
        return $this->rankForUserPaged($user, $limit, 0, $maxReasons);
    }

    /**
     * Classement paginé : on charge (limit+offset), on score, on tri, puis on découpe.
     * @return array<int, array{perfume: Perfume, score: float, reasons: string[]}>
     */
    public function rankForUserPaged(UserProfile $user, int $limit = 20, int $offset = 0, int $maxReasons = 8): array
    {
        $prefs = $this->buildPreferenceMaps($user);

        $repo = $this->em->getRepository(Perfume::class);
        if (method_exists($repo, 'findAllWithBrandAndNotes')) {
            /** @var Perfume[] $perfumes */
            $perfumes = $repo->findAllWithBrandAndNotes($limit + $offset, 0);
        } else {
            $qb = $repo->createQueryBuilder('p')
                ->addSelect('b','pn','n')
                ->join('p.brand','b')
                ->leftJoin('p.perfumeNotes','pn')
                ->leftJoin('pn.note','n')
                ->orderBy('b.name','ASC')->addOrderBy('p.name','ASC')
                ->setMaxResults($limit + $offset);
            /** @var Perfume[] $perfumes */
            $perfumes = $qb->getQuery()->getResult();
        }

        $scored = [];
        foreach ($perfumes as $p) {
            [$score, $reasons] = $this->scorePerfume($p, $prefs, $maxReasons);
            $score = round($score, 2); // arrondi propre
            $scored[] = ['perfume' => $p, 'score' => $score, 'reasons' => $reasons];
        }

        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);

        return $offset > 0
            ? array_slice($scored, $offset, $limit)
            : ($limit > 0 ? array_slice($scored, 0, $limit) : $scored);
    }

    /**
     * Construit les maps des préférences.
     * @return array{
     *   note: array<int,int>,
     *   brand: array<int,int>,
     *   concentration: array<string,int>,
     *   budget: ?array{min:?int,max:?int,currency:?string}
     * }
     */
    private function buildPreferenceMaps(UserProfile $user): array
    {
        // Notes (−5..+5)
        $note = [];
        $notePrefs = $this->findByUser(UserNotePreference::class, $user);
        foreach ($notePrefs as $pref) {
            if (($nid = $pref->getNote()?->getId()) !== null) {
                $note[$nid] = (int)$pref->getWeight();
            }
        }

        // Marque (−5..+5)
        $brand = [];
        $brandPrefs = $this->findByUser(UserBrandPreference::class, $user);
        foreach ($brandPrefs as $pref) {
            if (($bid = $pref->getBrand()?->getId()) !== null) {
                $brand[$bid] = (int)$pref->getWeight();
            }
        }

        // Concentration (0..5)
        $concentration = [];
        $concPrefs = $this->findByUser(UserConcentrationPreference::class, $user);
        foreach ($concPrefs as $pref) {
            $enum = $pref->getConcentration();
            $key  = strtoupper((string)($enum?->value));
            if ($key !== '') {
                $concentration[$key] = (int)$pref->getWeight();
            }
        }

        // Budget (min/max en cents + currency)
        $budgetMin = null; $budgetMax = null; $budgetCurrency = null;
        $budgetPref = $this->findOneByUser(UserBudgetPreference::class, $user);
        if ($budgetPref) {
            $budgetMin = $budgetPref->getMinCents();
            $budgetMax = $budgetPref->getMaxCents();
            $budgetCurrency = $budgetPref->getCurrency();
        }

        return [
            'note'          => $note,
            'brand'         => $brand,
            'concentration' => $concentration,
            // si pas de préférences budget → null (sinon bonus appliqué par erreur)
            'budget'        => $budgetPref
                ? ['min'=>$budgetMin,'max'=>$budgetMax,'currency'=>$budgetCurrency]
                : null,
        ];
    }
    private function buildPreferenceMapsForNonUser(array $user): array
    {
        // the array we got from non-user input
        /*
         * Data structure:
         * [    gender=> 'something',
                experience=> 'something',
                purpose=> 'something',
                aesthetic=> 'something',
                mood=> 'something',
                preferred_notes=> ['something','something',...],
                family=> 'something',
                projection=> 'something',
                concentration=> 'something',
                budget=> 'something'
            ]
         */
        // Notes (−5..+5)
        $note = [];
        // Notes are an array of strings in 'preferred_notes' key
        if (isset($user['preferred_notes']) && is_array($user['preferred_notes'])) {
            foreach ($user['preferred_notes'] as $noteName) {
                $notes[] = $this->em->getRepository(Note::class)->findOneBy(['name' => $noteName]);
            }
        }
        //$notePrefs = $this->findByUser(UserNotePreference::class, $user);
        foreach ($notes as $pref) {
            //if (($nid = $pref->getNote()?->getId()) !== null) {
                $note[$pref->getId()] = (int)$pref->getWeight();
            //}
        }
        
        // Marque (−5..+5)
        $brand = [];
        /* User from internet form has no brand preferences
         * $brandPrefs = $this->findByUser(UserBrandPreference::class, $user);
        foreach ($brandPrefs as $pref) {
            if (($bid = $pref->getBrand()?->getId()) !== null) {
                $brand[$bid] = (int)$pref->getWeight();
            }
        }
         */
        
        // Concentration (0..5)
        $concentration = [];
        $concPrefs = $this->findByUser(UserConcentrationPreference::class, $user);
        foreach ($concPrefs as $pref) {
            $enum = $pref->getConcentration();
            $key  = strtoupper((string)($enum?->value));
            if ($key !== '') {
                $concentration[$key] = (int)$pref->getWeight();
            }
        }
        
        // Budget (min/max en cents + currency)
        $budgetMin = null; $budgetMax = null; $budgetCurrency = null;
        $budgetPref = $this->findOneByUser(UserBudgetPreference::class, $user);
        if ($budgetPref) {
            $budgetMin = $budgetPref->getMinCents();
            $budgetMax = $budgetPref->getMaxCents();
            $budgetCurrency = $budgetPref->getCurrency();
        }
        
        return [
            'note'          => $note,
            'brand'         => $brand,
            'concentration' => $concentration,
            // si pas de préférences budget → null (sinon bonus appliqué par erreur)
            'budget'        => $budgetPref
                ? ['min'=>$budgetMin,'max'=>$budgetMax,'currency'=>$budgetCurrency]
                : null,
        ];
    }

    /** @param array $prefs voir buildPreferenceMaps() */
    private function scorePerfume(Perfume $p, array $prefs, int $maxReasons): array
    {
        $score = 0.0;
        $reasons = [];

        // 1) NOTES : HEART > TOP > BASE (poids utilisateur peuvent être négatifs => malus)
        if (!empty($prefs['note'])) {
            $notes = $p->getPerfumeNotes()->toArray();
            $order = ['HEART'=>3,'TOP'=>2,'BASE'=>1];
            usort($notes, function(PerfumeNote $a, PerfumeNote $b) use ($order){
                return ($order[strtoupper($b->getLayer())] ?? 0) <=> ($order[strtoupper($a->getLayer())] ?? 0);
            });
            foreach ($notes as $pn) {
                $nid = $pn->getNote()?->getId();
                if ($nid === null) { continue; }
                $userW = $prefs['note'][$nid] ?? 0; // −5..+5
                if ($userW === 0) { continue; }

                $layer = strtoupper($pn->getLayer());
                $coef  = self::LAYER_COEF[$layer] ?? 1.0;
                $delta = $userW * $coef;
                $score += $delta;

                if (count($reasons) < $maxReasons) {
                    $reasons[] = sprintf('%+0.2f %s (%s×%0.1f)',
                        $delta,
                        $pn->getNote()->getName(),
                        $layer,
                        $coef
                    );
                }
            }
        }

        // 2) MARQUE (−5..+5)
        if (!empty($prefs['brand'])) {
            $bid = $p->getBrand()?->getId();
            if ($bid !== null) {
                $brandW = (float)($prefs['brand'][$bid] ?? 0);
                if ($brandW !== 0.0) {
                    $delta = self::BRAND_COEF * $brandW;
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $reasons[] = sprintf('%+0.2f marque %s', $delta, $p->getBrand()->getName());
                    }
                }
            }
        }

        // 3) CONCENTRATION (0..5)
        if (!empty($prefs['concentration'])) {
            $concRaw = $p->getConcentration();
            if ($concRaw) {
                $concKey = method_exists(ConcEnum::class, 'parse')
                    ? (ConcEnum::parse($concRaw)?->value ?? strtoupper($concRaw))
                    : strtoupper($concRaw);

                $userConc = (float)($prefs['concentration'][$concKey] ?? 0); // 0..5
                if ($userConc > 0) {
                    $delta = self::CONC_COEF * ($userConc / 5.0);
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $reasons[] = sprintf('%+0.2f concentration %s', $delta, $concKey);
                    }
                }
            }
        }

        // 4) BUDGET (bonus si dans la fourchette, malus si hors fourchette)
        $budget = $prefs['budget'] ?? null;
        $priceCents = $this->getPriceCentsSafe($p);
        if ($budget !== null && $priceCents !== null) {
            $price = (int) $priceCents;
            $priceCur = strtoupper((string)($this->getPriceCurrencySafe($p) ?? ''));
            $min = $budget['min'];
            $max = $budget['max'];
            $userCur = strtoupper((string)($budget['currency'] ?? ''));

            // Si la devise ne correspond pas, on ignore (pas de bonus/malus)
            if ($userCur && $priceCur && $userCur !== $priceCur) {
                // ignore
            } else {
                $inRange =
                    ($min === null || $price >= $min) &&
                    ($max === null || $price <= $max);

                if ($inRange) {
                    $delta = self::BUDGET_BONUS;
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $reasons[] = sprintf('%+0.2f budget match (%s %s)',
                            $delta,
                            $this->formatCents($price, $priceCur ?: $userCur ?: 'EUR'),
                            $priceCur ?: $userCur ?: 'EUR'
                        );
                    }
                } else {
                    $delta = self::BUDGET_PENALTY;
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $hint = [];
                        if ($min !== null) { $hint[] = 'min '.$this->formatCents($min, $userCur ?: $priceCur ?: 'EUR'); }
                        if ($max !== null) { $hint[] = 'max '.$this->formatCents($max, $userCur ?: $priceCur ?: 'EUR'); }
                        $reasons[] = sprintf('%+0.2f budget out (%s %s; %s)',
                            $delta,
                            $this->formatCents($price, $priceCur ?: $userCur ?: 'EUR'),
                            $priceCur ?: $userCur ?: 'EUR',
                            implode(', ', $hint)
                        );
                    }
                }
            }
        }

        return [$score, $reasons];
    }

    private function formatCents(int $cents, string $currency): string
    {
        // Affichage simple : 12345 -> 123.45
        $amount = number_format($cents / 100, 2, '.', '');
        return $amount;
    }

    /**
     * Helper: récupère le prix en cents en gérant les différences de nommage.
     * Préférence: getListPriceCents() puis getPriceCents(); sinon null.
     */
    protected function getPriceCentsSafe(object $p): ?int
    {
        if (method_exists($p, 'getListPriceCents')) {
            /** @var ?int $v */
            $v = $p->getListPriceCents();
            return $v;
        }
        if (method_exists($p, 'getPriceCents')) {
            /** @var ?int $v */
            $v = $p->getPriceCents();
            return $v;
        }
        return null;
    }

    /**
     * Helper: récupère la devise associée au prix.
     * Préférence: getListPriceCurrency() puis getPriceCurrency(); sinon null.
     */
    protected function getPriceCurrencySafe(object $p): ?string
    {
        if (method_exists($p, 'getListPriceCurrency')) {
            /** @var ?string $v */
            $v = $p->getListPriceCurrency();
            return $v;
        }
        if (method_exists($p, 'getPriceCurrency')) {
            /** @var ?string $v */
            $v = $p->getPriceCurrency();
            return $v;
        }
        return null;
    }

    // --------------------------- Helpers ORM ---------------------------

    /**
     * Retourne le nom du champ d'association vers UserProfile pour une entité donnée.
     */
    private function getUserAssocField(string $entityClass): ?string
    {
        try {
            $cm = $this->em->getClassMetadata($entityClass);
            foreach ($cm->getAssociationNames() as $assoc) {
                try {
                    $target = $cm->getAssociationTargetClass($assoc);
                } catch (\Throwable $e) {
                    $target = $cm->associationMappings[$assoc]['targetEntity'] ?? null;
                }
                if ($target === UserProfile::class) {
                    return $assoc;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    /**
     * findBy préférences par utilisateur, auto-détection du champ d'association.
     * @return array<int, mixed>
     */
    private function findByUser(string $entityClass, UserProfile $user): array
    {
        $repo = $this->em->getRepository($entityClass);
        $field = $this->getUserAssocField($entityClass);
        if ($field) {
            try {
                return $repo->findBy([$field => $user]);
            } catch (\Throwable $e) {
                // fallback
            }
        }
        // Fallback sur quelques noms courants
        return $this->repoFindByUserFields($repo, ['user','userProfile','profile','owner'], $user);
    }

    /**
     * findOneBy préférences par utilisateur, auto-détection du champ d'association.
     */
    private function findOneByUser(string $entityClass, UserProfile $user): mixed
    {
        $repo = $this->em->getRepository($entityClass);
        $field = $this->getUserAssocField($entityClass);
        if ($field) {
            try {
                return $repo->findOneBy([$field => $user]);
            } catch (\Throwable $e) {
                // fallback
            }
        }
        return $this->repoFindOneByUserFields($repo, ['user','userProfile','profile','owner'], $user);
    }

    /**
     * Cherche des lignes liées à l'utilisateur en testant plusieurs noms de propriété.
     * @param object $repo Doctrine repository
     * @param string[] $fields
     * @param object $user
     * @return array<int, mixed>
     */
    private function repoFindByUserFields(object $repo, array $fields, object $user): array
    {
        foreach ($fields as $f) {
            try {
                $rows = $repo->findBy([$f => $user]);
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                // on ignore et on tente le champ suivant
            }
        }
        return [];
    }

    /** Variante pour un seul résultat (findOneBy) sur plusieurs champs. */
    private function repoFindOneByUserFields(object $repo, array $fields, object $user): mixed
    {
        foreach ($fields as $f) {
            try {
                $row = $repo->findOneBy([$f => $user]);
                if ($row !== null) {
                    return $row;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return null;
    }
    /*
     * Added methods for web ( non api ) usage
     */
    /**
     * Classement paginé : on charge (limit+offset), on score, on tri, puis on découpe.
     * @return array<int, array{perfume: Perfume, score: float, reasons: string[]}>
     */
    public function recommendForNonUser(array $user, int $limit = 20, int $offset = 0, int $maxReasons = 8): array
    {
        $prefs = $this->buildPreferenceMaps($user);
        
        $repo = $this->em->getRepository(Perfume::class);
        if (method_exists($repo, 'findAllWithBrandAndNotes')) {
            /** @var Perfume[] $perfumes */
            $perfumes = $repo->findAllWithBrandAndNotes($limit + $offset, 0);
        } else {
            $qb = $repo->createQueryBuilder('p')
                ->addSelect('b','pn','n')
                ->join('p.brand','b')
                ->leftJoin('p.perfumeNotes','pn')
                ->leftJoin('pn.note','n')
                ->orderBy('b.name','ASC')->addOrderBy('p.name','ASC')
                ->setMaxResults($limit + $offset);
            /** @var Perfume[] $perfumes */
            $perfumes = $qb->getQuery()->getResult();
        }
        
        $scored = [];
        foreach ($perfumes as $p) {
            [$score, $reasons] = $this->scorePerfume($p, $prefs, $maxReasons);
            $score = round($score, 2); // arrondi propre
            $scored[] = ['perfume' => $p, 'score' => $score, 'reasons' => $reasons];
        }
        
        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        
        return $offset > 0
            ? array_slice($scored, $offset, $limit)
            : ($limit > 0 ? array_slice($scored, 0, $limit) : $scored);
    }
}
