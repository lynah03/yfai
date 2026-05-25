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
use App\Entity\UserAccordPreference;
use App\Entity\Accord;
use App\Entity\Brand;
use App\Entity\UserSeasonPreference;
use App\Entity\UserOccasionPreference;
use App\Enum\Concentration as ConcEnum;
use App\Enum\Season;
use App\Enum\Occasion;
use App\Enum\Gender;
use App\Enum\MarketingGender;
use Doctrine\ORM\EntityManagerInterface;

class PerfumeMatcher
{
    // --- Coefficients ---

    // Notes (couches adoucies)
    private const LAYER_COEF = ['HEART' => 1.3, 'TOP' => 0.8, 'BASE' => 1.1];

    // Marque / Concentration
    private const BRAND_COEF = 0.8;   // × weight (−5..+5)
    private const CONC_COEF  = 0.6;   // × (userConc/5)

    // Budget (triangulaire)
    private const BUDGET_IN_RANGE_MAX = 0.8;   // bonus max au centre
    private const BUDGET_EDGE_BONUS   = 0.2;   // bonus aux bords de la plage
    private const BUDGET_OUT_PENALTY  = -1.0;  // malus hors plage (dégressif avec distance)

    // Accords : additif + multiplicatif borné
    private const ACCORD_ADD_COEF        = 0.25;  // additif par (weight/5) cumulé
    private const ACCORD_STEP            = 0.03;  // multiplicatif 3% par point
    private const ACCORD_MIN_FACTOR      = 0.85;
    private const ACCORD_MAX_FACTOR      = 1.20;
    private const ACCORD_SUM_CAP         = 5;
    private const ACCORD_MISMATCH_PENALTY = -0.40; // malus si aucun accord en commun

    // Season / Occasion : boost doux multiplicatif
    private const CONTEXT_STEP        = 0.03;
    private const CONTEXT_MIN_FACTOR  = 0.75;
    private const CONTEXT_MAX_FACTOR  = 1.15;
    private const CONTEXT_SUM_CAP     = 5;

    // Marketing gender : effet léger, non bloquant
    private const GENDER_ALIGN_BONUS      = 0.15; // parfum marketé vers ton genre
    private const GENDER_MISALIGN_PENALTY = -0.20; // parfum marketé à l'opposé
    private const GENDER_UNISEX_BONUS     = 0.08; // unisexe : petit bonus pour tout le monde
    private const GENDER_NB_UNISEX_EXTRA  = 0.02; // un peu plus pour NONBINARY/UNDISCLOSED

    // Caps pour éviter les emballements
    private const NOTES_COMPONENT_CAP = 12.0;  // contribution max (absolue) de la partie "notes"

    public function __construct(private EntityManagerInterface $em) {}

    // ------------------- Entrées publiques -------------------

    /**
     * @return array<int, array{perfume: Perfume, score: float, reasons: string[]}>
     */
    public function rankForUser(UserProfile $user, int $limit = 20, int $maxReasons = 8): array
    {
        return $this->rankForUserPaged($user, $limit, 0, $maxReasons);
    }

    /**
     * Tri **après** scoring : on charge tout, on score, on trie, puis on pagine.
     * @return array<int, array{perfume: Perfume, score: float, reasons: string[]}>
     */
    public function rankForUserPaged(UserProfile $user, int $limit = 20, int $offset = 0, int $maxReasons = 8): array
    {
        $prefs    = $this->buildPreferenceMaps($user);
        $perfumes = $this->loadAllPerfumes(); // plus de limite ici

        // IDF sur les notes du catalogue chargé
        $idf = $this->computeNoteIdf($perfumes);

        $scored = [];
        foreach ($perfumes as $p) {
            [$score, $reasons] = $this->scorePerfume($p, $prefs, $maxReasons, $idf);
            $scored[] = ['perfume' => $p, 'score' => round($score, 2), 'reasons' => $reasons];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Pagination **après** tri
        if ($offset > 0 || $limit > 0) {
            $scored = array_slice($scored, $offset, $limit > 0 ? $limit : null);
        }
        return $scored;
    }

    /*
     * Recommandation pour non-user (quiz public).
     */
    public function recommendForNonUser(array $input, int $limit = 20, int $offset = 0, int $maxReasons = 8): array
    {
        $prefs    = $this->buildPreferenceMapsForNonUser($input);
        $perfumes = $this->loadAllPerfumes();

        $idf = $this->computeNoteIdf($perfumes);

        $scored = [];
        foreach ($perfumes as $p) {
            [$score, $reasons] = $this->scorePerfume($p, $prefs, $maxReasons, $idf);
            $scored[] = ['perfume' => $p, 'score' => round($score, 2), 'reasons' => $reasons];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        if ($offset > 0 || $limit > 0) {
            $scored = array_slice($scored, $offset, $limit > 0 ? $limit : null);
        }
        return $scored;
    }

    // ------------------- Chargement catalogue -------------------

    /**
     * Charge tout le catalogue avec brand + notes (+ accords si mapping présent)
     * @return Perfume[]
     */
    private function loadAllPerfumes(): array
    {
        $repo = $this->em->getRepository(Perfume::class);

        if (method_exists($repo, 'findAllWithBrandAndNotes')) {
            return $repo->findAllWithBrandAndNotes(null);
        }

        $qb = $repo->createQueryBuilder('p')
            ->addSelect('b','pn','n')
            ->join('p.brand', 'b')
            ->leftJoin('p.perfumeNotes', 'pn')
            ->leftJoin('pn.note', 'n')
            ->orderBy('b.name', 'ASC')->addOrderBy('p.name', 'ASC');

        try {
            $cm = $this->em->getClassMetadata(Perfume::class);
            if ($cm->hasAssociation('accords')) {
                $qb->addSelect('a')->leftJoin('p.accords', 'a');
            }
            if ($cm->hasAssociation('seasons')) {
                $qb->addSelect('s')->leftJoin('p.seasons', 's');
            }
            if ($cm->hasAssociation('occasions')) {
                $qb->addSelect('o')->leftJoin('p.occasions', 'o');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        /** @var Perfume[] $perfumes */
        $perfumes = $qb->getQuery()->getResult();
        return $perfumes;
    }

    // ------------------- Construction des préférences -------------------

    /**
     * @return array{
     *   note: array<int,int>,
     *   brand: array<int,int>,
     *   concentration: array<string,int>,
     *   budget: ?array{min:?int,max:?int,currency:?string},
     *   accord: array<int,int>,
     *   season: array<string,int>,
     *   occasion: array<string,int>,
     *   user_gender: ?Gender
     * }
     */
    private function buildPreferenceMaps(UserProfile $user): array
    {
        // Notes
        $note = [];
        $notePrefs = $this->findByUser(UserNotePreference::class, $user);
        foreach ($notePrefs as $pref) {
            if (($nid = $pref->getNote()?->getId()) !== null) {
                $note[$nid] = (int) $pref->getWeight();
            }
        }

        // Brands
        $brand = [];
        $brandPrefs = $this->findByUser(UserBrandPreference::class, $user);
        foreach ($brandPrefs as $pref) {
            if (($bid = $pref->getBrand()?->getId()) !== null) {
                $brand[$bid] = (int) $pref->getWeight();
            }
        }

        // Concentration
        $concentration = [];
        $concPrefs = $this->findByUser(UserConcentrationPreference::class, $user);
        foreach ($concPrefs as $pref) {
            $enum = $pref->getConcentration();
            $key  = $enum instanceof ConcEnum ? strtoupper($enum->value) : strtoupper((string)$enum);
            if ($key !== '') {
                $concentration[$key] = (int)$pref->getWeight();
            }
        }

        // Budget
        $budget = null;
        $budgetPref = $this->findOneByUser(UserBudgetPreference::class, $user);
        if ($budgetPref) {
            $budget = [
                'min'      => $budgetPref->getMinCents(),
                'max'      => $budgetPref->getMaxCents(),
                'currency' => $budgetPref->getCurrency(),
            ];
        }

        // Accords
        $accord = [];
        $accordPrefs = $this->findByUser(UserAccordPreference::class, $user);
        foreach ($accordPrefs as $pref) {
            $acc = $pref->getAccord();
            if ($acc && $acc->getId() !== null) {
                $accord[$acc->getId()] = (int)$pref->getWeight();
            }
        }

        // Seasons
        $season = [];
        $seasonPrefs = $this->findByUser(UserSeasonPreference::class, $user);
        foreach ($seasonPrefs as $pref) {
            $s = $pref->getSeason();
            if ($s instanceof Season) {
                $season[$s->value] = (int)$pref->getWeight();
            }
        }

        // Occasions
        $occasion = [];
        $occasionPrefs = $this->findByUser(UserOccasionPreference::class, $user);
        foreach ($occasionPrefs as $pref) {
            $o = $pref->getOccasion();
            if ($o instanceof Occasion) {
                $occasion[$o->value] = (int)$pref->getWeight();
            }
        }

        // Genre utilisateur (pour croiser avec marketingGender)
        $userGender = null;
        if (method_exists($user, 'getGender')) {
            $g = $user->getGender();
            if ($g instanceof Gender) {
                $userGender = $g;
            }
        }

        return [
            'note'         => $note,
            'brand'        => $brand,
            'concentration'=> $concentration,
            'budget'       => $budget,
            'accord'       => $accord,
            'season'       => $season,
            'occasion'     => $occasion,
            'user_gender'  => $userGender,
        ];
    }

    /**
     * @return array{
     *   note: array<int,int>,
     *   brand: array<int,int>,
     *   concentration: array<string,int>,
     *   budget: ?array{min:?int,max:?int,currency:?string},
     *   accord: array<int,int>,
     *   season: array<string,int>,
     *   occasion: array<string,int>,
     *   user_gender: ?Gender
     * }
     */
    private function buildPreferenceMapsForNonUser(array $input): array
    {
        // Notes
        // preferred_notes => positive signal
        // disliked_notes  => negative signal
        $note = [];
        $noteRepo = $this->em->getRepository(Note::class);

        $applyNotePreference = function (mixed $items, int $weight) use (&$note, $noteRepo): void {
            if (!is_array($items)) {
                return;
            }

            foreach ($items as $noteName) {
                if (!is_string($noteName) || trim($noteName) === '') {
                    continue;
                }

                $noteName = trim($noteName);

                $noteEntity = $noteRepo->findOneBy(['name' => $noteName]);

                if (!$noteEntity) {
                    $noteEntity = $noteRepo->createQueryBuilder('n')
                        ->where('LOWER(n.name) = :name')
                        ->setParameter('name', mb_strtolower($noteName))
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                }

                if ($noteEntity && $noteEntity->getId() !== null) {
                    $note[$noteEntity->getId()] = $weight;
                }
            }
        };

        $applyNotePreference($input['preferred_notes'] ?? [], 3);
        $applyNotePreference($input['disliked_notes'] ?? [], -4);

        // Brands
        $brand = [];
        if (!empty($input['preferred_brands']) && is_array($input['preferred_brands'])) {
            $brandRepo = $this->em->getRepository(Brand::class);
            foreach ($input['preferred_brands'] as $brandName) {
                if (!is_string($brandName) || $brandName === '') continue;
                $brandEntity = $brandRepo->findOneBy(['name' => $brandName]);
                if ($brandEntity && $brandEntity->getId() !== null) {
                    $brand[$brandEntity->getId()] = 3;
                }
            }
        }

        // Concentration
        $concentration = [];
        if (!empty($input['concentration']) && is_string($input['concentration'])) {
            $enum = ConcEnum::parse($input['concentration']);

            if ($enum instanceof ConcEnum) {
                $concentration[$enum->value] = 4;
            } else {
                $key = strtoupper(trim($input['concentration']));
                if ($key !== '') {
                    $concentration[$key] = 3;
                }
            }
        }

        // Budget
        $budget = null;
        $minCents = null; $maxCents = null; $currency = 'EUR';
        if (isset($input['budget_min_cents']) || isset($input['budget_max_cents'])) {
            $minCents = isset($input['budget_min_cents']) ? (int)$input['budget_min_cents'] : null;
            $maxCents = isset($input['budget_max_cents']) ? (int)$input['budget_max_cents'] : null;
        } else {
            if (isset($input['budget_min']) && is_numeric($input['budget_min'])) $minCents = (int) round($input['budget_min'] * 100);
            if (isset($input['budget_max']) && is_numeric($input['budget_max'])) $maxCents = (int) round($input['budget_max'] * 100);
            if (!isset($input['budget_max']) && isset($input['budget']) && is_numeric($input['budget'])) $maxCents = (int) round($input['budget'] * 100);
        }
        if ($minCents !== null || $maxCents !== null) {
            $budget = ['min'=>$minCents,'max'=>$maxCents,'currency'=>$currency];
        }

        // Accords
        $accord = [];
        if (!empty($input['preferred_accords']) && is_array($input['preferred_accords'])) {
            $accordRepo = $this->em->getRepository(Accord::class);

            foreach ($input['preferred_accords'] as $accName) {
                if (!is_string($accName) || trim($accName) === '') {
                    continue;
                }

                $raw = trim($accName);
                $code = strtoupper(str_replace([' ', '-'], '_', $raw));

                $accEntity = $accordRepo->findOneBy(['code' => $code]);

                if (!$accEntity) {
                    $accEntity = $accordRepo->createQueryBuilder('a')
                        ->where('LOWER(a.label) = :label')
                        ->orWhere('LOWER(a.code) = :code')
                        ->setParameter('label', mb_strtolower($raw))
                        ->setParameter('code', mb_strtolower($code))
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                }

                if ($accEntity && $accEntity->getId() !== null) {
                    $accord[$accEntity->getId()] = 3;
                }
            }
        }

        // Seasons
        $season = [];
        if (!empty($input['preferred_seasons']) && is_array($input['preferred_seasons']) && enum_exists(Season::class)) {
            foreach ($input['preferred_seasons'] as $label) {
                if (!is_string($label) || trim($label) === '') {
                    continue;
                }

                $enum = Season::parse($label);

                if ($enum instanceof Season) {
                    $season[$enum->value] = 3;
                }
            }
        }

        // Occasions
        $occasion = [];
        if (!empty($input['preferred_occasions']) && is_array($input['preferred_occasions']) && enum_exists(Occasion::class)) {
            foreach ($input['preferred_occasions'] as $label) {
                if (!is_string($label) || trim($label) === '') {
                    continue;
                }

                $enum = Occasion::parse($label);

                if ($enum instanceof Occasion) {
                    $occasion[$enum->value] = 3;
                }
            }
        }

        // Gender styling for non-user widget flow.
        // This is a light signal against perfume marketingGender.
        $userGender = null;
        $genderRaw = $input['gender'] ?? $input['user_gender'] ?? $input['gender_preference'] ?? null;

        if (is_string($genderRaw) && trim($genderRaw) !== '') {
            $userGender = Gender::parse($genderRaw);
        }

        return [
            'note'         => $note,
            'brand'        => $brand,
            'concentration'=> $concentration,
            'budget'       => $budget,
            'accord'       => $accord,
            'season'       => $season,
            'occasion'     => $occasion,
            'user_gender'  => $userGender,
        ];
    }

    // ------------------- Scoring -------------------

    /**
     * @param array<int,float> $idf NoteID => idf weight
     * @return array{0: float, 1: string[]}
     */
    private function scorePerfume(Perfume $p, array $prefs, int $maxReasons, array $idf = []): array
    {
        $score   = 0.0;
        $reasons = [];

        // 1) NOTES ------------------------------------------------------------
        $notesDelta = 0.0;
        if (!empty($prefs['note'])) {
            $notes = $p->getPerfumeNotes()->toArray();
            $order = ['HEART' => 3, 'TOP' => 2, 'BASE' => 1];

            usort($notes, function (PerfumeNote $a, PerfumeNote $b) use ($order) {
                return ($order[strtoupper($b->getLayer())] ?? 0) <=> ($order[strtoupper($a->getLayer())] ?? 0);
            });

            $countNotes = max(1, count($notes));
            foreach ($notes as $pn) {
                $nid = $pn->getNote()?->getId();
                if ($nid === null) continue;

                $userW = $prefs['note'][$nid] ?? 0;
                if ($userW === 0) continue;

                $layer = strtoupper($pn->getLayer());
                $coef  = self::LAYER_COEF[$layer] ?? 1.0;

                $intensity = method_exists($pn, 'getIntensity') ? ((int)$pn->getIntensity() ?: 1) : 1;
                $intensityCoef = 0.8 + 0.2 * min(3, max(1, $intensity));

                $idfWeight = $idf[$nid] ?? 1.0;

                $delta = ($userW * $coef * $intensityCoef * $idfWeight) / $countNotes;
                $notesDelta += $delta;

                if (count($reasons) < $maxReasons) {
                    $reasons[] = sprintf(
                        '%+0.2f %s (%s×%.1f, int×%.1f, idf×%.2f)',
                        $delta,
                        $pn->getNote()->getName(),
                        $layer,
                        $coef,
                        $intensityCoef,
                        $idfWeight
                    );
                }
            }

            $notesDelta = max(-self::NOTES_COMPONENT_CAP, min(self::NOTES_COMPONENT_CAP, $notesDelta));
            $score += $notesDelta;
        }

        // 2) MARQUE -----------------------------------------------------------
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

        // 3) CONCENTRATION ----------------------------------------------------
        if (!empty($prefs['concentration'])) {
            $concRaw = $p->getConcentration();
            $concKey = $concRaw instanceof ConcEnum ? strtoupper($concRaw->value) : ($concRaw !== null ? strtoupper((string)$concRaw) : '');
            if ($concKey !== '') {
                $userConc = (float)($prefs['concentration'][$concKey] ?? 0);
                if ($userConc > 0) {
                    $delta = self::CONC_COEF * ($userConc / 5.0);
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $reasons[] = sprintf('%+0.2f concentration %s', $delta, $concKey);
                    }
                }
            }
        }

        // 4) BUDGET -----------------------------------------------------------
        $budget = $prefs['budget'] ?? null;
        $priceCents = $this->getPriceCentsSafe($p);
        if ($budget !== null && $priceCents !== null) {
            $price = (int)$priceCents;
            $priceCur = strtoupper((string)($this->getPriceCurrencySafe($p) ?? ''));
            $min = $budget['min']; $max = $budget['max'];
            $userCur = strtoupper((string)($budget['currency'] ?? ''));

            if (!$userCur || !$priceCur || $userCur === $priceCur) {
                $delta = $this->budgetTriangularDelta($price, $min, $max);
                $score += $delta;
                if (count($reasons) < $maxReasons) {
                    $label = $delta >= 0 ? 'budget match' : 'budget out';
                    $reasons[] = sprintf(
                        '%+0.2f %s (%s %s)',
                        $delta,
                        $label,
                        $this->formatCents($price, $priceCur ?: $userCur ?: 'EUR'),
                        $priceCur ?: $userCur ?: 'EUR'
                    );
                }
            }
        }

        // 5) SEASONS ----------------------------------------------------------
        if (!empty($prefs['season']) && method_exists($p, 'getSeasons') && $score !== 0.0) {
            $userSeasons = $prefs['season'];
            $perfumeSeasons = [];
            foreach ((array)$p->getSeasons() as $s) {
                if ($s instanceof Season)      $perfumeSeasons[] = $s->value;
                elseif (is_string($s))         $perfumeSeasons[] = strtoupper($s);
            }
            $sum = 0;
            foreach ($perfumeSeasons as $sv) {
                if (isset($userSeasons[$sv])) $sum += (int)$userSeasons[$sv];
            }
            if ($sum !== 0) {
                $sum    = max(-self::CONTEXT_SUM_CAP, min(self::CONTEXT_SUM_CAP, $sum));
                $factor = max(self::CONTEXT_MIN_FACTOR, min(self::CONTEXT_MAX_FACTOR, 1.0 + $sum*self::CONTEXT_STEP));
                $old = $score; $score *= $factor; $delta = $score - $old;
                if (count($reasons) < $maxReasons) {
                    $label = $sum > 0 ? 'saison adaptée à ton usage' : 'moins aligné avec ta saison cible';
                    $reasons[] = sprintf('%+0.2f %s', $delta, $label);
                }
            }
        }

        // 6) OCCASIONS --------------------------------------------------------
        if (!empty($prefs['occasion']) && method_exists($p, 'getOccasions') && $score !== 0.0) {
            $userOcc = $prefs['occasion'];
            $perfumeOcc = [];
            foreach ((array)$p->getOccasions() as $o) {
                if ($o instanceof Occasion)    $perfumeOcc[] = $o->value;
                elseif (is_string($o))         $perfumeOcc[] = strtoupper($o);
            }
            $sum = 0;
            foreach ($perfumeOcc as $ov) {
                if (isset($userOcc[$ov])) $sum += (int)$userOcc[$ov];
            }
            if ($sum !== 0) {
                $sum    = max(-self::CONTEXT_SUM_CAP, min(self::CONTEXT_SUM_CAP, $sum));
                $factor = max(self::CONTEXT_MIN_FACTOR, min(self::CONTEXT_MAX_FACTOR, 1.0 + $sum*self::CONTEXT_STEP));
                $old = $score; $score *= $factor; $delta = $score - $old;
                if (count($reasons) < $maxReasons) {
                    $label = $sum > 0 ? 'parfait pour tes occasions' : 'moins adapté à tes occasions';
                    $reasons[] = sprintf('%+0.2f %s', $delta, $label);
                }
            }
        }

        // 7) ACCORDS ----------------------------------------------------------
        if (!empty($prefs['accord']) && method_exists($p, 'getAccords')) {
            $accordPrefs = $prefs['accord']; // [accordId => weight]

            $perfumeAccords = $p->getAccords();
            $hasPerfumeAccords = count($perfumeAccords) > 0;

            // Additif
            $add = 0.0;
            $overlapSum = 0;
            foreach ($perfumeAccords as $accord) {
                $aid = $accord->getId();
                if ($aid !== null && isset($accordPrefs[$aid])) {
                    $w = (int)$accordPrefs[$aid];
                    $add += self::ACCORD_ADD_COEF * ($w / 5.0);
                    $overlapSum += $w;
                }
            }

            if ($add !== 0.0) {
                $score += $add;
                if (count($reasons) < $maxReasons) {
                    $reasons[] = sprintf('%+0.2f accords (additif)', $add);
                }
            }

            // Multiplicatif si on a du score
            if ($score !== 0.0 && $overlapSum !== 0) {
                $sum    = max(-self::ACCORD_SUM_CAP, min(self::ACCORD_SUM_CAP, $overlapSum));
                $factor = max(self::ACCORD_MIN_FACTOR, min(self::ACCORD_MAX_FACTOR, 1.0 + $sum*self::ACCORD_STEP));
                $old = $score; $score *= $factor; $delta = $score - $old;
                if (count($reasons) < $maxReasons) {
                    $label = $sum > 0 ? 'accords alignés avec tes préférences' : 'accords moins alignés avec tes préférences';
                    $reasons[] = sprintf('%+0.2f %s', $delta, $label);
                }
            }

            // Malus si AUCUN accord en commun
            if ($score !== 0.0 && $hasPerfumeAccords && $overlapSum === 0) {
                $score += self::ACCORD_MISMATCH_PENALTY;
                if (count($reasons) < $maxReasons) {
                    $reasons[] = sprintf('%+0.2f accords peu alignés avec tes préférences', self::ACCORD_MISMATCH_PENALTY);
                }
            }
        }

        // 8) MARKETING GENDER (léger, non bloquant) --------------------------
        if (array_key_exists('user_gender', $prefs)
            && $prefs['user_gender'] instanceof Gender
            && method_exists($p, 'getMarketingGender')
        ) {
            /** @var Gender $userGender */
            $userGender = $prefs['user_gender'];
            $mg = $p->getMarketingGender();

            if ($mg instanceof MarketingGender) {
                $delta = $this->computeGenderDelta($userGender, $mg);
                if ($delta !== 0.0) {
                    $score += $delta;
                    if (count($reasons) < $maxReasons) {
                        $label = $delta > 0
                            ? 'aligné avec ton genre'
                            : 'moins aligné avec ton genre';
                        $reasons[] = sprintf('%+0.2f %s', $delta, $label);
                    }
                }
            }
        }

        return [$score, $reasons];
    }

    // ------------------- Helpers calcul -------------------

    /**
     * @param Perfume[] $perfumes
     * @return array<int,float> noteId => idfWeight (~0.9..1.3)
     */
    private function computeNoteIdf(array $perfumes): array
    {
        $df = []; $N = max(1, count($perfumes));
        foreach ($perfumes as $p) {
            $seen = [];
            foreach ($p->getPerfumeNotes() as $pn) {
                $nid = $pn->getNote()?->getId();
                if ($nid === null || isset($seen[$nid])) continue;
                $seen[$nid] = true;
                $df[$nid] = ($df[$nid] ?? 0) + 1;
            }
        }
        $idf = [];
        foreach ($df as $nid => $d) {
            $w = 1.0 + \log(($N+1)/($d+1)) * 0.25;
            $idf[$nid] = max(0.9, min(1.3, $w));
        }
        return $idf;
    }

    private function budgetTriangularDelta(int $price, ?int $min, ?int $max): float
    {
        if ($min === null && $max === null) {
            return 0.0;
        }
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        if (($min === null || $price >= $min) && ($max === null || $price <= $max)) {
            if ($min === null || $max === null || $min === $max) {
                return self::BUDGET_EDGE_BONUS;
            }
            $center = ($min + $max) / 2.0;
            $radius = ($max - $min) / 2.0;
            $dist   = abs($price - $center);
            $x      = max(0.0, 1.0 - ($dist / $radius));
            return self::BUDGET_EDGE_BONUS + ($x * (self::BUDGET_IN_RANGE_MAX - self::BUDGET_EDGE_BONUS));
        }

        $edge = $min !== null && $price < $min ? $min : ($max !== null ? $max : $price);
        $gap  = max(1.0, abs($price - $edge));
        $norm = $gap / 5000.0; // 5000 cents = 50 €
        $pen  = self::BUDGET_OUT_PENALTY * min(1.0, 0.5 + 0.5 * \tanh($norm));
        return $pen;
    }

    private function computeGenderDelta(Gender $userGender, MarketingGender $mg): float
    {
        // Unisexe : toujours légèrement positif
        if ($mg === MarketingGender::UNISEX) {
            if (in_array($userGender, [Gender::NONBINARY, Gender::UNDISCLOSED], true)) {
                return self::GENDER_UNISEX_BONUS + self::GENDER_NB_UNISEX_EXTRA;
            }
            return self::GENDER_UNISEX_BONUS;
        }

        // User non-binaire / non déclaré : on ne pénalise jamais
        if (in_array($userGender, [Gender::NONBINARY, Gender::UNDISCLOSED], true)) {
            return 0.0;
        }

        // Alignements simples
        if ($mg === MarketingGender::WOMEN && $userGender === Gender::FEMALE) {
            return self::GENDER_ALIGN_BONUS;
        }
        if ($mg === MarketingGender::MEN && $userGender === Gender::MALE) {
            return self::GENDER_ALIGN_BONUS;
        }

        // Sinon mismatch léger
        return self::GENDER_MISALIGN_PENALTY;
    }

    private function formatCents(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected function getPriceCentsSafe(object $p): ?int
    {
        if (method_exists($p, 'getListPriceCents')) return $p->getListPriceCents();
        if (method_exists($p, 'getPriceCents'))     return $p->getPriceCents();
        return null;
    }

    protected function getPriceCurrencySafe(object $p): ?string
    {
        if (method_exists($p, 'getListPriceCurrency')) return $p->getListPriceCurrency();
        if (method_exists($p, 'getPriceCurrency'))     return $p->getPriceCurrency();
        return null;
    }

    // ------------------- Helpers ORM -------------------

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
        } catch (\Throwable $e) {}
        return null;
    }

    private function findByUser(string $entityClass, UserProfile $user): array
    {
        $repo = $this->em->getRepository($entityClass);
        $field = $this->getUserAssocField($entityClass);
        if ($field) {
            try { return $repo->findBy([$field => $user]); } catch (\Throwable $e) {}
        }
        return $this->repoFindByUserFields($repo, ['user','userProfile','profile','owner'], $user);
    }

    private function findOneByUser(string $entityClass, UserProfile $user): mixed
    {
        $repo = $this->em->getRepository($entityClass);
        $field = $this->getUserAssocField($entityClass);
        if ($field) {
            try { return $repo->findOneBy([$field => $user]); } catch (\Throwable $e) {}
        }
        return $this->repoFindOneByUserFields($repo, ['user','userProfile','profile','owner'], $user);
    }

    private function repoFindByUserFields(object $repo, array $fields, object $user): array
    {
        foreach ($fields as $f) {
            try {
                $rows = $repo->findBy([$f => $user]);
                if (!empty($rows)) return $rows;
            } catch (\Throwable $e) {}
        }
        return [];
    }

    private function repoFindOneByUserFields(object $repo, array $fields, object $user): mixed
    {
        foreach ($fields as $f) {
            try {
                $row = $repo->findOneBy([$f => $user]);
                if ($row !== null) return $row;
            } catch (\Throwable $e) {}
        }
        return null;
    }
}
