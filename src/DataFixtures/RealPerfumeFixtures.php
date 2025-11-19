<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Perfume;
use App\Entity\PerfumeNote;
use App\Entity\Note;
use App\Entity\Accord;
use App\Entity\UserProfile;

use App\Entity\UserNotePreference;
use App\Entity\UserBrandPreference;
use App\Entity\UserConcentrationPreference;
use App\Entity\UserBudgetPreference;
use App\Entity\UserAccordPreference;

use App\Enum\Concentration;
use App\Enum\Gender;
use App\Enum\MarketingGender;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RealPerfumeFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        /*
         * 1) BRANDS (réels + Maison Cipro + marques abordables)
         */
        $brandsData = [
            ['Dior', 'France'],
            ['Chanel', 'France'],
            ['Guerlain', 'France'],
            ['Tom Ford', 'USA'],
            ['Creed', 'France'],
            ['KILIAN Paris', 'France'],
            ['Roja Parfums', 'UK'],
            ['Maison Francis Kurkdjian', 'France'],
            ['Le Labo', 'USA'],
            ['Diptyque', 'France'],
            ['Jo Malone London', 'UK'],
            ['Yves Saint Laurent Beauté', 'France'],
            ['Hermès', 'France'],
            ['Mugler', 'France'],
            ['Paco Rabanne', 'Spain'],
            ['Maison Margiela', 'France'],
            ['Lancôme', 'France'],
            ['Versace', 'Italy'],
            ['Maison Cipro', 'France'],
            ['Byredo', 'Sweden'],

            // Marques plus abordables
            ['Zara', 'Spain'],
            ['Yves Rocher', 'France'],
            ['The Body Shop', 'UK'],
            ['Bath & Body Works', 'USA'],
        ];

        $brands = [];
        foreach ($brandsData as [$name, $country]) {
            $b = new Brand();
            $b->setName($name);
            if (method_exists($b, 'setCountry')) {
                $b->setCountry($country);
            }
            if (method_exists($b, 'setDescription')) {
                $b->setDescription(null);
            }
            $manager->persist($b);
            $brands[$name] = $b;
        }

        /*
         * 2) NOTES
         */
        $noteNames = [
            'Bergamot', 'Lemon', 'Grapefruit',
            'Lavender', 'Iris', 'Rose', 'Jasmine', 'Orange Blossom', 'Neroli',
            'Sandalwood', 'Cedar', 'Vetiver', 'Patchouli',
            'Vanilla', 'Tonka Bean', 'Amber', 'Musk',
            'Oud', 'Incense', 'Leather',
            'Coffee', 'Cacao',
            'Fig', 'Marine', 'Green Tea', 'Blackcurrant', 'Pear', 'Apple',
            'Spices',
        ];
        $notes = [];
        foreach ($noteNames as $name) {
            $n = new Note();
            $n->setName($name);
            $manager->persist($n);
            $notes[$name] = $n;
        }

        /*
         * 3) ACCORDS
         */
        $accordData = [
            ['FRESH', 'Frais'],
            ['CITRUS', 'Hespéridé'],
            ['FLORAL', 'Floral'],
            ['WOODY', 'Boisé'],
            ['AMBERY', 'Ambré'],
            ['ORIENTAL', 'Oriental'],
            ['GOURMAND', 'Gourmand'],
            ['MUSKY', 'Musqué'],
            ['LEATHERY', 'Cuiré'],
            ['AROMATIC', 'Aromatique'],
            ['GREEN', 'Vert'],
            ['SPICY', 'Épicé'],
            ['FRUITY', 'Fruité'],
            ['MARINE', 'Marin'],
            ['SMOKY', 'Fumé'],
        ];

        $accords = [];
        foreach ($accordData as [$code, $label]) {
            $a = new Accord();
            $a->setCode($code);
            $a->setLabel($label);
            if (method_exists($a, 'setDescription')) {
                $a->setDescription(null);
            }
            $manager->persist($a);
            $accords[$code] = $a;
        }

        $getNote = fn(string $name): ?Note => $notes[$name] ?? null;
        $getAccord = fn(string $code): ?Accord => $accords[$code] ?? null;

        /*
         * 4) PERFUMES
         *
         * Structure:
         * [brand, name, year, conc, price, marketingGender, accordCodes[], noteLayers[]]
         */
        $perfumesData = [
            // Dior
            ['Dior', 'Sauvage', 2015, 'EDT', 95, 'MEN',
                ['FRESH', 'SPICY', 'WOODY', 'AROMATIC'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Lavender', 'Spices'],
                    'BASE' => ['Cedar', 'Amber', 'Musk'],
                ],
            ],
            ['Dior', 'J\'adore', 1999, 'EDP', 130, 'WOMEN',
                ['FLORAL', 'FRUITY'],
                [
                    'TOP' => ['Pear', 'Bergamot'],
                    'HEART' => ['Jasmine', 'Rose'],
                    'BASE' => ['Musk'],
                ],
            ],

            // Chanel
            ['Chanel', 'Bleu de Chanel', 2010, 'EDT', 110, 'MEN',
                ['CITRUS', 'WOODY', 'AROMATIC'],
                [
                    'TOP' => ['Grapefruit', 'Lemon'],
                    'HEART' => ['Lavender', 'Spices'],
                    'BASE' => ['Sandalwood', 'Cedar'],
                ],
            ],
            ['Chanel', 'Coco Mademoiselle', 2001, 'EDP', 135, 'WOMEN',
                ['CITRUS', 'FLORAL', 'AMBERY'],
                [
                    'TOP' => ['Orange Blossom', 'Bergamot'],
                    'HEART' => ['Jasmine', 'Rose'],
                    'BASE' => ['Patchouli', 'Vanilla', 'Musk'],
                ],
            ],

            // Guerlain
            ['Guerlain', 'Shalimar', 1925, 'EDP', 120, 'WOMEN',
                ['ORIENTAL', 'AMBERY', 'GOURMAND'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Iris', 'Jasmine'],
                    'BASE' => ['Vanilla', 'Tonka Bean', 'Leather'],
                ],
            ],
            ['Guerlain', 'Mon Guerlain', 2017, 'EDP', 105, 'WOMEN',
                ['FLORAL', 'AMBERY'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Lavender', 'Jasmine'],
                    'BASE' => ['Vanilla', 'Sandalwood'],
                ],
            ],

            // Tom Ford
            ['Tom Ford', 'Oud Wood', 2007, 'EDP', 250, 'MEN',
                ['WOODY', 'SMOKY', 'ORIENTAL'],
                [
                    'TOP' => ['Spices'],
                    'HEART' => ['Oud', 'Sandalwood'],
                    'BASE' => ['Amber', 'Vanilla'],
                ],
            ],
            ['Tom Ford', 'Black Orchid', 2006, 'EDP', 150, 'UNISEX',
                ['GOURMAND', 'ORIENTAL', 'FLORAL'],
                [
                    'TOP' => ['Blackcurrant'],
                    'HEART' => ['Jasmine'],
                    'BASE' => ['Patchouli', 'Cacao', 'Musk'],
                ],
            ],

            // Creed
            ['Creed', 'Aventus', 2010, 'EDP', 280, 'MEN',
                ['FRUITY', 'WOODY', 'SMOKY'],
                [
                    'TOP' => ['Bergamot', 'Apple', 'Blackcurrant'],
                    'HEART' => ['Jasmine'],
                    'BASE' => ['Musk', 'Patchouli', 'Vanilla'],
                ],
            ],

            // KILIAN Paris
            ['KILIAN Paris', 'Love, don\'t be shy', 2007, 'EDP', 210, 'WOMEN',
                ['GOURMAND', 'FLORAL'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Orange Blossom', 'Jasmine'],
                    'BASE' => ['Vanilla', 'Musk'],
                ],
            ],
            ['KILIAN Paris', 'Black Phantom', 2017, 'EDP', 230, 'UNISEX',
                ['GOURMAND', 'SMOKY'],
                [
                    'TOP' => ['Coffee'],
                    'HEART' => ['Cacao', 'Spices'],
                    'BASE' => ['Vanilla', 'Sandalwood'],
                ],
            ],
            ['KILIAN Paris', 'Dark Lord - "Ex Tenebris Lux"', 2018, 'EDP', 260, 'MEN',
                ['SMOKY', 'LEATHERY', 'WOODY'],
                [
                    'TOP'   => ['Bergamot', 'Spices'],
                    'HEART' => ['Jasmine', 'Leather'],
                    'BASE'  => ['Vetiver', 'Musk'],
                ],
            ],

            // Roja
            ['Roja Parfums', 'Elysium', 2017, 'EDP', 260, 'MEN',
                ['FRESH', 'CITRUS', 'AROMATIC'],
                [
                    'TOP' => ['Grapefruit', 'Lemon'],
                    'HEART' => ['Lavender', 'Jasmine'],
                    'BASE' => ['Vetiver', 'Cedar'],
                ],
            ],
            ['Roja Parfums', 'Enigma', 2013, 'PARFUM', 320, 'UNISEX',
                ['AMBERY', 'ORIENTAL', 'GOURMAND'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Jasmine'],
                    'BASE' => ['Vanilla', 'Amber', 'Spices'],
                ],
            ],

            // Maison Francis Kurkdjian
            ['Maison Francis Kurkdjian', 'Baccarat Rouge 540', 2015, 'EDP', 235, 'UNISEX',
                ['AMBERY', 'WOODY'],
                [
                    'TOP' => ['Spices'],
                    'HEART' => ['Jasmine'],
                    'BASE' => ['Cedar', 'Amber'],
                ],
            ],

            // Le Labo
            ['Le Labo', 'Santal 33', 2011, 'EDP', 210, 'UNISEX',
                ['WOODY', 'AROMATIC'],
                [
                    'TOP' => ['Spices'],
                    'HEART' => ['Sandalwood'],
                    'BASE' => ['Cedar', 'Leather'],
                ],
            ],

            // Diptyque
            ['Diptyque', 'Philosykos', 1996, 'EDT', 130, 'UNISEX',
                ['GREEN', 'FRUITY', 'WOODY'],
                [
                    'TOP' => ['Fig'],
                    'HEART' => ['Fig', 'Green Tea'],
                    'BASE' => ['Cedar'],
                ],
            ],

            // Jo Malone London
            ['Jo Malone London', 'Wood Sage & Sea Salt', 2014, 'EDT', 110, 'UNISEX',
                ['MARINE', 'WOODY', 'FRESH'],
                [
                    'TOP' => ['Marine'],
                    'HEART' => ['Green Tea'],
                    'BASE' => ['Cedar'],
                ],
            ],

            // Yves Saint Laurent
            ['Yves Saint Laurent Beauté', 'Libre', 2019, 'EDP', 125, 'WOMEN',
                ['FLORAL', 'AMBERY'],
                [
                    'TOP' => ['Lavender', 'Bergamot'],
                    'HEART' => ['Orange Blossom', 'Jasmine'],
                    'BASE' => ['Vanilla', 'Musk'],
                ],
            ],

            // Hermès
            ['Hermès', 'Terre d\'Hermès', 2006, 'EDT', 105, 'MEN',
                ['WOODY', 'CITRUS'],
                [
                    'TOP' => ['Grapefruit', 'Orange Blossom'],
                    'HEART' => ['Spices'],
                    'BASE' => ['Vetiver', 'Cedar'],
                ],
            ],

            // Mugler
            ['Mugler', 'Angel', 1992, 'EDP', 110, 'WOMEN',
                ['GOURMAND', 'ORIENTAL'],
                [
                    'TOP' => ['Bergamot'],
                    'HEART' => ['Blackcurrant'],
                    'BASE' => ['Vanilla', 'Patchouli', 'Cacao'],
                ],
            ],

            // Maison Margiela
            ['Maison Margiela', 'Replica Jazz Club', 2013, 'EDT', 120, 'MEN',
                ['GOURMAND', 'SMOKY', 'LEATHERY'],
                [
                    'TOP' => ['Spices'],
                    'HEART' => ['Coffee'],
                    'BASE' => ['Vanilla', 'Leather', 'Amber'],
                ],
            ],

            // Lancôme
            ['Lancôme', 'La Vie est Belle', 2012, 'EDP', 115, 'WOMEN',
                ['GOURMAND', 'FLORAL'],
                [
                    'TOP' => ['Pear', 'Blackcurrant'],
                    'HEART' => ['Iris', 'Jasmine'],
                    'BASE' => ['Vanilla', 'Tonka Bean'],
                ],
            ],

            // Paco Rabanne
            ['Paco Rabanne', '1 Million', 2008, 'EDT', 95, 'MEN',
                ['GOURMAND', 'SPICY'],
                [
                    'TOP' => ['Grapefruit'],
                    'HEART' => ['Spices'],
                    'BASE' => ['Amber', 'Leather'],
                ],
            ],

            // Versace
            ['Versace', 'Dylan Blue', 2016, 'EDT', 90, 'MEN',
                ['FRESH', 'AROMATIC', 'WOODY'],
                [
                    'TOP' => ['Bergamot', 'Grapefruit'],
                    'HEART' => ['Green Tea'],
                    'BASE' => ['Musk', 'Patchouli'],
                ],
            ],

            // Byredo
            ['Byredo', 'Gypsy Water', 2008, 'EDP', 190, 'UNISEX',
                ['WOODY', 'AROMATIC'],
                [
                    'TOP' => ['Bergamot', 'Lemon'],
                    'HEART' => ['Incense'],
                    'BASE' => ['Sandalwood', 'Vanilla'],
                ],
            ],

            // Maison Cipro — Caesar
            ['Maison Cipro', 'Caesar', 2025, 'PARFUM', 250, 'MEN',
                ['SPICY', 'LEATHERY', 'AMBERY', 'MUSKY', 'WOODY'],
                [
                    'TOP' => ['Spices', 'Musk'],
                    'HEART' => ['Rose', 'Leather'],
                    'BASE' => ['Vetiver', 'Patchouli'],
                ],
            ],

            // Maison Cipro — Nero
            ['Maison Cipro', 'Nero', 2025, 'PARFUM', 260, 'MEN',
                ['SMOKY', 'LEATHERY', 'WOODY', 'AMBERY'],
                [
                    'TOP' => ['Bergamot', 'Spices'],
                    'HEART' => ['Incense', 'Leather'],
                    'BASE' => ['Oud', 'Patchouli', 'Amber'],
                ],
            ],

            /*
             * Marques plus abordables (≤ 50€)
             */

            // Zara
            ['Zara', 'Vetiver Pamplemousse', 2023, 'EDT', 30, 'UNISEX',
                ['FRESH', 'CITRUS', 'WOODY'],
                [
                    'TOP' => ['Grapefruit', 'Bergamot'],
                    'HEART' => ['Green Tea'],
                    'BASE' => ['Vetiver', 'Cedar'],
                ],
            ],

            // Yves Rocher
            ['Yves Rocher', 'Mon Evidence', 2018, 'EDP', 45, 'WOMEN',
                ['FLORAL', 'FRUITY'],
                [
                    'TOP'   => ['Pear', 'Bergamot'],
                    'HEART' => ['Rose', 'Jasmine'],
                    'BASE'  => ['Vanilla', 'Musk'],
                ],
            ],

            // The Body Shop
            ['The Body Shop', 'White Musk', 1981, 'EDT', 35, 'UNISEX',
                ['MUSKY', 'FLORAL'],
                [
                    'TOP'   => ['Bergamot'],
                    'HEART' => ['Jasmine'],
                    'BASE'  => ['Musk', 'Cedar'],
                ],
            ],

            // Bath & Body Works
            ['Bath & Body Works', 'Ocean', 2020, 'EDT', 40, 'MEN',
                ['FRESH', 'MARINE', 'WOODY'],
                [
                    'TOP'   => ['Marine', 'Lemon'],
                    'HEART' => ['Green Tea'],
                    'BASE'  => ['Cedar', 'Musk'],
                ],
            ],
        ];

        $perfumes = [];

        foreach ($perfumesData as [$brandName, $name, $year, $conc, $price, $mgCode, $accordCodes, $noteLayers]) {
            if (!isset($brands[$brandName])) {
                continue;
            }

            $p = new Perfume();
            $p->setBrand($brands[$brandName]);
            $p->setName($name);
            $p->setReleaseYear($year);
            $p->setListPriceCents($price * 100);
            $p->setListPriceCurrency('EUR');
            if (method_exists($p, 'setDescription')) {
                $p->setDescription(null);
            }

            // Concentration enum
            if (enum_exists(Concentration::class)) {
                $upper = strtoupper($conc);
                foreach (Concentration::cases() as $case) {
                    if ($case->value === $upper) {
                        $p->setConcentration($case);
                        break;
                    }
                }
            }

            // Marketing gender enum
            if ($mgCode !== null && enum_exists(MarketingGender::class)) {
                $mgUpper = strtoupper($mgCode);
                foreach (MarketingGender::cases() as $mgCase) {
                    if ($mgCase->value === $mgUpper) {
                        $p->setMarketingGender($mgCase);
                        break;
                    }
                }
            }

            // Accords
            if (method_exists($p, 'addAccord')) {
                foreach ($accordCodes as $code) {
                    $acc = $getAccord($code);
                    if ($acc) {
                        $p->addAccord($acc);
                    }
                }
            }

            // Notes
            foreach ($noteLayers as $layer => $noteList) {
                foreach ($noteList as $noteName) {
                    $n = $getNote($noteName);
                    if (!$n) {
                        continue;
                    }

                    $pn = new PerfumeNote();
                    if (method_exists($pn, 'setPerfume')) {
                        $pn->setPerfume($p);
                    }
                    if (method_exists($pn, 'setNote')) {
                        $pn->setNote($n);
                    }
                    if (method_exists($pn, 'setLayer')) {
                        $pn->setLayer(strtoupper($layer));
                    }
                    if (method_exists($pn, 'setIntensity')) {
                        $pn->setIntensity(2);
                    }

                    $manager->persist($pn);

                    if (method_exists($p, 'addPerfumeNote')) {
                        $p->addPerfumeNote($pn);
                    }
                }
            }

            $manager->persist($p);
            $perfumes[$name] = $p;
        }

        /*
         * 5) USERS DEMO + PREFS (Clara / Alex / Noir Collector)
         */

        // Clara
        $user1 = new UserProfile();
        $user1->setName('Clara Gourmand');
        if (enum_exists(Gender::class)) {
            if (defined(Gender::class.'::FEMALE')) {
                $user1->setGender(Gender::FEMALE);
            } else {
                $user1->setGender(Gender::UNDISCLOSED);
            }
        }
        $user1->setVerified(true);
        $user1->setRoles(['ROLE_CUSTOMER_USER']);
        $user1->setEmail('clara@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $manager->persist($user1);

        // Alex
        $user2 = new UserProfile();
        $user2->setName('Alex Fresh');
        if (enum_exists(Gender::class)) {
            if (defined(Gender::class.'::MALE')) {
                $user2->setGender(Gender::MALE);
            } else {
                $user2->setGender(Gender::UNDISCLOSED);
            }
        }
        $user2->setVerified(true);
        $user2->setRoles(['ROLE_CUSTOMER_USER']);
        $user2->setEmail('alex@example.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $manager->persist($user2);

        // Noir Collector
        $user3 = new UserProfile();
        $user3->setName('Noir Collector');
        if (enum_exists(Gender::class)) {
            if (defined(Gender::class.'::MALE')) {
                $user3->setGender(Gender::MALE);
            } else {
                $user3->setGender(Gender::UNDISCLOSED);
            }
        }
        $user3->setVerified(true);
        $user3->setRoles(['ROLE_CUSTOMER_USER']);
        $user3->setEmail('noir@example.com');
        $user3->setPassword($this->passwordHasher->hashPassword($user3, 'password'));
        $manager->persist($user3);

        /*
         * Notes prefs
         */
        if (class_exists(UserNotePreference::class)) {
            // Clara : gourmand
            foreach (['Vanilla', 'Tonka Bean', 'Coffee', 'Cacao'] as $nName) {
                if (!isset($notes[$nName])) continue;
                $pref = new UserNotePreference();
                if (!$this->attachUserToPreference($pref, $user1)) continue;
                if (method_exists($pref, 'setNote')) {
                    $pref->setNote($notes[$nName]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(4);
                }
                $manager->persist($pref);
            }

            // Alex : frais / marin
            foreach (['Bergamot', 'Marine', 'Green Tea'] as $nName) {
                if (!isset($notes[$nName])) continue;
                $pref = new UserNotePreference();
                if (!$this->attachUserToPreference($pref, $user2)) continue;
                if (method_exists($pref, 'setNote')) {
                    $pref->setNote($notes[$nName]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(4);
                }
                $manager->persist($pref);
            }

            // Noir Collector : cuir / sombre
            foreach (['Leather', 'Vetiver', 'Oud', 'Incense', 'Spices'] as $nName) {
                if (!isset($notes[$nName])) continue;
                $pref = new UserNotePreference();
                if (!$this->attachUserToPreference($pref, $user3)) continue;
                if (method_exists($pref, 'setNote')) {
                    $pref->setNote($notes[$nName]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(5);
                }
                $manager->persist($pref);
            }
        }

        /*
         * Brand prefs
         */
        if (class_exists(UserBrandPreference::class)) {
            // Clara : Maison Cipro + KILIAN
            foreach (['Maison Cipro', 'KILIAN Paris'] as $bn) {
                if (!isset($brands[$bn])) continue;
                $pref = new UserBrandPreference();
                if (!$this->attachUserToPreference($pref, $user1)) continue;
                if (method_exists($pref, 'setBrand')) {
                    $pref->setBrand($brands[$bn]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(3);
                }
                $manager->persist($pref);
            }

            // Alex : Dior + Creed
            foreach (['Dior', 'Creed'] as $bn) {
                if (!isset($brands[$bn])) continue;
                $pref = new UserBrandPreference();
                if (!$this->attachUserToPreference($pref, $user2)) continue;
                if (method_exists($pref, 'setBrand')) {
                    $pref->setBrand($brands[$bn]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(3);
                }
                $manager->persist($pref);
            }

            // Noir Collector : Maison Cipro + KILIAN
            foreach (['Maison Cipro', 'KILIAN Paris'] as $bn) {
                if (!isset($brands[$bn])) continue;
                $pref = new UserBrandPreference();
                if (!$this->attachUserToPreference($pref, $user3)) continue;
                if (method_exists($pref, 'setBrand')) {
                    $pref->setBrand($brands[$bn]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(4);
                }
                $manager->persist($pref);
            }
        }

        /*
         * Accord prefs
         */
        if (class_exists(UserAccordPreference::class)) {
            // Clara : gourmand / ambré / cuir
            foreach (['GOURMAND', 'AMBERY', 'LEATHERY'] as $code) {
                if (!isset($accords[$code])) continue;
                $pref = new UserAccordPreference();
                if (!$this->attachUserToPreference($pref, $user1)) continue;
                if (method_exists($pref, 'setAccord')) {
                    $pref->setAccord($accords[$code]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(4);
                }
                $manager->persist($pref);
            }

            // Alex : frais / marin / citrus
            foreach (['FRESH', 'MARINE', 'CITRUS'] as $code) {
                if (!isset($accords[$code])) continue;
                $pref = new UserAccordPreference();
                if (!$this->attachUserToPreference($pref, $user2)) continue;
                if (method_exists($pref, 'setAccord')) {
                    $pref->setAccord($accords[$code]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(4);
                }
                $manager->persist($pref);
            }

            // Noir Collector : cuiré / fumé / boisé / ambré
            foreach (['LEATHERY', 'SMOKY', 'WOODY', 'AMBERY'] as $code) {
                if (!isset($accords[$code])) continue;
                $pref = new UserAccordPreference();
                if (!$this->attachUserToPreference($pref, $user3)) continue;
                if (method_exists($pref, 'setAccord')) {
                    $pref->setAccord($accords[$code]);
                }
                if (method_exists($pref, 'setWeight')) {
                    $pref->setWeight(5);
                }
                $manager->persist($pref);
            }
        }

        /*
         * Budget prefs
         */
        if (class_exists(UserBudgetPreference::class)) {
            // Clara : mid-high
            $b1 = new UserBudgetPreference();
            if ($this->attachUserToPreference($b1, $user1)) {
                if (method_exists($b1, 'setMinCents')) $b1->setMinCents(8000);
                if (method_exists($b1, 'setMaxCents')) $b1->setMaxCents(30000);
                if (method_exists($b1, 'setCurrency')) $b1->setCurrency('EUR');
                $manager->persist($b1);
            }

            // Alex : raisonnable (0–150€)
            $b2 = new UserBudgetPreference();
            if ($this->attachUserToPreference($b2, $user2)) {
                if (method_exists($b2, 'setMinCents')) $b2->setMinCents(0);
                if (method_exists($b2, 'setMaxCents')) $b2->setMaxCents(15000);
                if (method_exists($b2, 'setCurrency')) $b2->setCurrency('EUR');
                $manager->persist($b2);
            }

            // Noir Collector : haut de gamme
            $b3 = new UserBudgetPreference();
            if ($this->attachUserToPreference($b3, $user3)) {
                if (method_exists($b3, 'setMinCents')) $b3->setMinCents(15000); // 150€
                if (method_exists($b3, 'setMaxCents')) $b3->setMaxCents(40000); // 400€
                if (method_exists($b3, 'setCurrency')) $b3->setCurrency('EUR');
                $manager->persist($b3);
            }
        }

        /*
         * Concentration prefs
         */
        if (class_exists(UserConcentrationPreference::class) && enum_exists(Concentration::class)) {
            // Clara : EDP
            $c1 = new UserConcentrationPreference();
            if ($this->attachUserToPreference($c1, $user1)
                && method_exists($c1, 'setConcentration')
                && method_exists($c1, 'setWeight')
            ) {
                $c1->setConcentration(Concentration::EDP);
                $c1->setWeight(4);
                $manager->persist($c1);
            }

            // Alex : EDT
            $c2 = new UserConcentrationPreference();
            if ($this->attachUserToPreference($c2, $user2)
                && method_exists($c2, 'setConcentration')
                && method_exists($c2, 'setWeight')
            ) {
                $c2->setConcentration(Concentration::EDT);
                $c2->setWeight(3);
                $manager->persist($c2);
            }

            // Noir Collector : PARFUM (ou EDP si besoin)
            $c3 = new UserConcentrationPreference();
            if ($this->attachUserToPreference($c3, $user3)
                && method_exists($c3, 'setConcentration')
                && method_exists($c3, 'setWeight')
            ) {
                $conc = defined(Concentration::class.'::PARFUM')
                    ? Concentration::PARFUM
                    : Concentration::EDP;

                $c3->setConcentration($conc);
                $c3->setWeight(5);
                $manager->persist($c3);
            }
        }

        $manager->flush();
    }

    /**
     * Lie un UserProfile à une entité de préférence
     * en testant plusieurs conventions : setUserProfile, setUser, setProfile, setOwner.
     */
    private function attachUserToPreference(object $pref, UserProfile $user): bool
    {
        foreach (['setUserProfile', 'setUser', 'setProfile', 'setOwner'] as $method) {
            if (method_exists($pref, $method)) {
                $pref->{$method}($user);
                return true;
            }
        }

        return false;
    }
}
