<?php
namespace App\Service;

use App\Entity\UserProfile;
use App\Entity\{
    Note,
    Brand,
    UserNotePreference,
    UserBrandPreference,
    UserConcentrationPreference,
    UserOccasionPreference,
    UserSeasonPreference,
    UserBudgetPreference
};
use App\Enum\{Concentration, Occasion, Season};
use Doctrine\ORM\EntityManagerInterface;

class UserPreferenceManager
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @param array<int,array{note?:string,noteId?:int,weight:int}> $items */
    public function putNotes(UserProfile $user, array $items): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $items) {
            $repo = $this->em->getRepository(UserNotePreference::class);
            $noteRepo = $this->em->getRepository(Note::class);

            $existing = $repo->findBy(['user' => $user]);
            $existingByNoteId = [];
            foreach ($existing as $pref) {
                $existingByNoteId[$pref->getNote()->getId()] = $pref;
            }

            $desired = []; // noteId => weight (-5..+5, 0 supprime)
            foreach ($items as $it) {
                $w = max(-5, min(5, (int)($it['weight'] ?? 0)));
                $noteId = $it['noteId'] ?? null;

                if (!$noteId && !empty($it['note'])) {
                    $n = $noteRepo->findOneBy(['name' => (string)$it['note']]);
                    if ($n) $noteId = $n->getId();
                }
                if ($noteId) $desired[(int)$noteId] = $w;
            }

            // delete missing or zero
            foreach ($existingByNoteId as $nid => $pref) {
                if (!array_key_exists($nid, $desired) || $desired[$nid] === 0) {
                    $this->em->remove($pref);
                    unset($existingByNoteId[$nid]);
                }
            }

            // upsert desired
            foreach ($desired as $nid => $w) {
                if ($w === 0) continue;
                if (isset($existingByNoteId[$nid])) {
                    $existingByNoteId[$nid]->setWeight($w);
                } else {
                    $note = $noteRepo->find($nid);
                    if (!$note) continue;
                    $pref = (new UserNotePreference())->setUser($user)->setNote($note)->setWeight($w);
                    $this->em->persist($pref);
                }
            }

            return ['updated' => count($desired), 'errors' => []];
        });
    }

    /** @param array<int,array{brand?:string,brandId?:int,weight:int}> $items */
    public function putBrands(UserProfile $user, array $items): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $items) {
            $repo = $this->em->getRepository(UserBrandPreference::class);
            $brandRepo = $this->em->getRepository(Brand::class);

            $existing = $repo->findBy(['user' => $user]);
            $existingByBrandId = [];
            foreach ($existing as $pref) {
                $existingByBrandId[$pref->getBrand()->getId()] = $pref;
            }

            $desired = []; // brandId => weight (-5..+5, 0 supprime)
            foreach ($items as $it) {
                $w = max(-5, min(5, (int)($it['weight'] ?? 0)));
                $brandId = $it['brandId'] ?? null;

                if (!$brandId && !empty($it['brand'])) {
                    $b = $brandRepo->findOneBy(['name' => (string)$it['brand']]);
                    if ($b) $brandId = $b->getId();
                }
                if ($brandId) $desired[(int)$brandId] = $w;
            }

            foreach ($existingByBrandId as $bid => $pref) {
                if (!array_key_exists($bid, $desired) || $desired[$bid] === 0) {
                    $this->em->remove($pref);
                    unset($existingByBrandId[$bid]);
                }
            }

            foreach ($desired as $bid => $w) {
                if ($w === 0) continue;
                if (isset($existingByBrandId[$bid])) {
                    $existingByBrandId[$bid]->setWeight($w);
                } else {
                    $brand = $brandRepo->find($bid);
                    if (!$brand) continue;
                    $pref = (new UserBrandPreference())->setUser($user)->setBrand($brand)->setWeight($w);
                    $this->em->persist($pref);
                }
            }

            return ['updated' => count($desired), 'errors' => []];
        });
    }

    /** @param array<int,array{concentration:string,weight:int}> $items */
    public function putConcentration(UserProfile $user, array $items): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $items) {
            $repo = $this->em->getRepository(UserConcentrationPreference::class);

            $existing = $repo->findBy(['user' => $user]);
            $existingByKey = [];
            foreach ($existing as $pref) {
                $existingByKey[$pref->getConcentration()->value] = $pref;
            }

            $desired = []; // concentrationValue => weight (0..5, 0 supprime)
            foreach ($items as $it) {
                $raw = strtoupper((string)($it['concentration'] ?? ''));
                $enum = Concentration::tryFrom($raw) ?? Concentration::parse($raw);
                if (!$enum) continue;

                $w = max(0, min(5, (int)($it['weight'] ?? 0)));
                $desired[$enum->value] = $w;
            }

            foreach ($existingByKey as $key => $pref) {
                if (!array_key_exists($key, $desired) || $desired[$key] === 0) {
                    $this->em->remove($pref);
                    unset($existingByKey[$key]);
                }
            }

            foreach ($desired as $key => $w) {
                if ($w === 0) continue;
                if (isset($existingByKey[$key])) {
                    $existingByKey[$key]->setWeight($w);
                } else {
                    $pref = (new UserConcentrationPreference())
                        ->setUser($user)
                        ->setConcentration(Concentration::from($key))
                        ->setWeight($w);
                    $this->em->persist($pref);
                }
            }

            return ['updated' => count($desired), 'errors' => []];
        });
    }

    /** @param array<int,array{occasion:string,weight:int}> $items */
    public function putOccasions(UserProfile $user, array $items): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $items) {
            $repo = $this->em->getRepository(UserOccasionPreference::class);

            $existing = $repo->findBy(['user' => $user]);
            $existingByKey = [];
            foreach ($existing as $pref) {
                $existingByKey[$pref->getOccasion()->value] = $pref;
            }

            $desired = []; // occasion => weight (0..5, 0 supprime)
            foreach ($items as $it) {
                $raw = strtoupper((string)($it['occasion'] ?? ''));
                $enum = Occasion::tryFrom($raw);
                if (!$enum) continue;

                $w = max(0, min(5, (int)($it['weight'] ?? 0)));
                $desired[$enum->value] = $w;
            }

            foreach ($existingByKey as $key => $pref) {
                if (!array_key_exists($key, $desired) || $desired[$key] === 0) {
                    $this->em->remove($pref);
                    unset($existingByKey[$key]);
                }
            }

            foreach ($desired as $key => $w) {
                if ($w === 0) continue;
                if (isset($existingByKey[$key])) {
                    $existingByKey[$key]->setWeight($w);
                } else {
                    $pref = (new UserOccasionPreference())
                        ->setUser($user)
                        ->setOccasion(Occasion::from($key))
                        ->setWeight($w);
                    $this->em->persist($pref);
                }
            }

            return ['updated' => count($desired), 'errors' => []];
        });
    }

    /** @param array<int,array{season:string,weight:int}> $items */
    public function putSeasons(UserProfile $user, array $items): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $items) {
            $repo = $this->em->getRepository(UserSeasonPreference::class);

            $existing = $repo->findBy(['user' => $user]);
            $existingByKey = [];
            foreach ($existing as $pref) {
                $existingByKey[$pref->getSeason()->value] = $pref;
            }

            $desired = []; // season => weight (0..5, 0 supprime)
            foreach ($items as $it) {
                $raw = strtoupper((string)($it['season'] ?? ''));
                $enum = Season::tryFrom($raw);
                if (!$enum) continue;

                $w = max(0, min(5, (int)($it['weight'] ?? 0)));
                $desired[$enum->value] = $w;
            }

            foreach ($existingByKey as $key => $pref) {
                if (!array_key_exists($key, $desired) || $desired[$key] === 0) {
                    $this->em->remove($pref);
                    unset($existingByKey[$key]);
                }
            }

            foreach ($desired as $key => $w) {
                if ($w === 0) continue;
                if (isset($existingByKey[$key])) {
                    $existingByKey[$key]->setWeight($w);
                } else {
                    $pref = (new UserSeasonPreference())
                        ->setUser($user)
                        ->setSeason(Season::from($key))
                        ->setWeight($w);
                    $this->em->persist($pref);
                }
            }

            return ['updated' => count($desired), 'errors' => []];
        });
    }

    /**
     * Met à jour la préférence de budget (ou supprime si min & max sont null).
     * @return array{updated: bool, errors: array<int,string>}
     */
    public function putBudget(UserProfile $user, ?int $minCents, ?int $maxCents, ?string $currency = 'EUR'): array
    {
        return $this->em->wrapInTransaction(function () use ($user, $minCents, $maxCents, $currency) {
            $errors = [];

            if ($minCents !== null && $minCents < 0) {
                $errors[] = 'minCents must be >= 0 or null';
            }
            if ($maxCents !== null && $maxCents < 0) {
                $errors[] = 'maxCents must be >= 0 or null';
            }
            if ($minCents !== null && $maxCents !== null && $maxCents < $minCents) {
                $errors[] = 'maxCents must be >= minCents';
            }

            // normalise currency
            $currency = $currency ? strtoupper(trim($currency)) : 'EUR';
            if (strlen($currency) !== 3) {
                $errors[] = 'currency must be a 3-letter ISO code';
            }

            if ($errors) {
                return ['updated' => false, 'errors' => $errors];
            }

            $repo = $this->em->getRepository(UserBudgetPreference::class);
            /** @var UserBudgetPreference|null $pref */
            $pref = $repo->findOneBy(['user' => $user]);

            // reset si aucune borne fournie
            if ($minCents === null && $maxCents === null) {
                if ($pref) {
                    $this->em->remove($pref);
                }
                $this->em->flush();
                return ['updated' => true, 'errors' => []];
            }

            if (!$pref) {
                $pref = new UserBudgetPreference();
                $pref->setUser($user);
                $this->em->persist($pref);
            }

            $pref->setMinCents($minCents);
            $pref->setMaxCents($maxCents);
            $pref->setCurrency($currency);

            $this->em->flush();

            return ['updated' => true, 'errors' => []];
        });
    }
}
