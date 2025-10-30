<?php
namespace App\Command;

use App\Entity\Brand;
use App\Entity\Note;
use App\Entity\Perfume;
use App\Entity\PerfumeNote;
use App\Enum\MarketingGender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:full-demo', description: 'Crée 5 marques et >=5 parfums par marque, inclut Caesar (Maison Cipro) avec notes officielles')]
class SeedFullDemoCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->em;

        // -------- Helpers locaux (find-or-create) --------
        $brandOf = function (string $name, ?string $country = null, ?string $desc = null): Brand {
            $em  = $this->em;
            $r   = $em->getRepository(Brand::class);
            $b   = $r->findOneBy(['name' => $name]) ?? (new Brand())->setName($name);
            if ($country !== null) { $b->setCountry($country); }
            if ($desc !== null)    { $b->setDescription($desc); }
            $em->persist($b);
            return $b;
        };

        $noteOf = function (string $name, ?string $family = null): Note {
            $em = $this->em;
            $r  = $em->getRepository(Note::class);
            $n  = $r->findOneBy(['name' => $name]) ?? (new Note())->setName($name);
            if ($family !== null) { $n->setFamily($family); }
            $em->persist($n);
            return $n;
        };

        $perfumeOf = function (Brand $brand, string $name, ?int $year = null, ?string $concentration = null, ?MarketingGender $mg = null, ?string $desc = null): Perfume {
            $em = $this->em;
            $r  = $em->getRepository(Perfume::class);
            $p  = $r->findOneBy(['brand' => $brand, 'name' => $name]);
            if (!$p) {
                $p = (new Perfume())
                    ->setBrand($brand)
                    ->setName($name);
            }
            if ($year !== null)         { $p->setReleaseYear($year); }
            if ($concentration !== null){ $p->setConcentration(strtoupper($concentration)); } // EDP/EDT/PARFUM/EXTRAIT
            if ($mg !== null)           { $p->setMarketingGender($mg); }
            if ($desc !== null)         { $p->setDescription($desc); }
            $em->persist($p);
            return $p;
        };

        $linkNote = function (Perfume $p, Note $n, string $layer): void {
            $layer = strtoupper($layer); // 'TOP' | 'HEART' | 'BASE'
            // éviter doublons (uniq perfume_id/note_id/layer)
            foreach ($p->getPerfumeNotes() as $pn) {
                if ($pn->getNote()?->getId() === $n->getId() && strtoupper($pn->getLayer()) === $layer) {
                    return;
                }
            }
            $pn = (new PerfumeNote())
                ->setPerfume($p)
                ->setNote($n)
                ->setLayer($layer)
                ->setIntensity(3);
            $p->addPerfumeNote($pn);
            $this->em->persist($pn);
        };

        // -------- Notes de base (pool commun) --------
        $bergamot  = $noteOf('Bergamot', 'Citrus');
        $grapefruit= $noteOf('Grapefruit', 'Citrus');
        $lemon     = $noteOf('Lemon', 'Citrus');
        $rose      = $noteOf('Rose', 'Floral');
        $jasmine   = $noteOf('Jasmine', 'Floral');
        $iris      = $noteOf('Iris', 'Floral');
        $violet    = $noteOf('Violet', 'Floral');
        $leather   = $noteOf('Leather', 'Leather');
        $oud       = $noteOf('Oud', 'Woody');
        $cedar     = $noteOf('Cedar', 'Woody');
        $sandal    = $noteOf('Sandalwood', 'Woody');
        $vetiver   = $noteOf('Vetiver', 'Woody');
        $patchouli = $noteOf('Patchouli', 'Woody');
        $amber     = $noteOf('Amber', 'Oriental');
        $vanilla   = $noteOf('Vanilla', 'Gourmand');
        $musk      = $noteOf('Musk', 'Musk');
        $pepper    = $noteOf('Pink Pepper', 'Spicy');
        $spices    = $noteOf('Spices', 'Spicy');
        $lavender  = $noteOf('Lavender', 'Aromatic');

        // -------- Marques --------
        $cipro  = $brandOf('Maison Cipro', 'IT', 'Luxury Renaissance Italian Brand');
        $roja   = $brandOf('Roja Dove', 'UK');
        $chanel = $brandOf('Chanel', 'FR');
        $dior   = $brandOf('Dior', 'FR');
        $tomford= $brandOf('Tom Ford', 'US');

        // ===== Maison Cipro (inclut Caesar – notes officielles) =====
        $caesar = $perfumeOf($cipro, 'Caesar', 2025, 'EXTRAIT', MarketingGender::UNISEX);
        // Notes “officielles” : Head: spices, musk — Heart: rose, leather — Base: vetiver, patchouli
        $linkNote($caesar, $spices,   'TOP');
        $linkNote($caesar, $musk,     'TOP');
        $linkNote($caesar, $rose,     'HEART');
        $linkNote($caesar, $leather,  'HEART');
        $linkNote($caesar, $vetiver,  'BASE');
        $linkNote($caesar, $patchouli,'BASE');

        // 4 autres parfums (fictifs pour la démo)
        $c1 = $perfumeOf($cipro, 'Aurelius', 2025, 'EDP', MarketingGender::UNISEX);
        $linkNote($c1, $bergamot, 'TOP'); $linkNote($c1, $jasmine, 'HEART'); $linkNote($c1, $sandal, 'BASE');

        $c2 = $perfumeOf($cipro, 'Nero', 2025, 'PARFUM', MarketingGender::UNISEX);
        $linkNote($c2, $pepper, 'TOP'); $linkNote($c2, $iris, 'HEART'); $linkNote($c2, $amber, 'BASE');

        $c3 = $perfumeOf($cipro, 'Lyna', 2025, 'EDP', MarketingGender::WOMEN);
        $linkNote($c3, $lemon, 'TOP'); $linkNote($c3, $leather, 'HEART'); $linkNote($c3, $cedar, 'BASE');

        $c4 = $perfumeOf($cipro, 'Alexander', 2025, 'EDP', MarketingGender::UNISEX);
        $linkNote($c4, $grapefruit, 'TOP'); $linkNote($c4, $rose, 'HEART'); $linkNote($c4, $musk, 'BASE');

        // ===== Roja Dove (5) — démo (noms génériques) =====
        $r1 = $perfumeOf($roja, 'Amber Aoud', 2012, 'PARFUM', MarketingGender::UNISEX);
        $linkNote($r1, $rose, 'HEART'); $linkNote($r1, $oud, 'BASE'); $linkNote($r1, $amber, 'BASE');

        $r2 = $perfumeOf($roja, 'Enigma', 2013, 'EDP', MarketingGender::UNISEX);
        $linkNote($r2, $bergamot, 'TOP'); $linkNote($r2, $rose, 'HEART'); $linkNote($r2, $musk, 'BASE');

        $r3 = $perfumeOf($roja, 'Elysium', 2017, 'EDP', MarketingGender::UNISEX);
        $linkNote($r3, $grapefruit, 'TOP'); $linkNote($r3, $vetiver, 'BASE'); $linkNote($r3, $cedar, 'BASE');

        $r4 = $perfumeOf($roja, 'Oligarch', 2019, 'EDP', MarketingGender::UNISEX);
        $linkNote($r4, $lemon, 'TOP'); $linkNote($r4, $jasmine, 'HEART'); $linkNote($r4, $sandal, 'BASE');

        $r5 = $perfumeOf($roja, 'Reckless', 2014, 'EDP', MarketingGender::UNISEX);
        $linkNote($r5, $pepper, 'TOP'); $linkNote($r5, $rose, 'HEART'); $linkNote($r5, $vanilla, 'BASE');

        // ===== Chanel (5) — démo =====
        $ch1 = $perfumeOf($chanel, 'Coco Mademoiselle', 2001, 'EDP', MarketingGender::WOMEN);
        $linkNote($ch1, $bergamot, 'TOP'); $linkNote($ch1, $rose, 'HEART'); $linkNote($ch1, $musk, 'BASE');

        $ch2 = $perfumeOf($chanel, 'Bleu de Chanel', 2010, 'EDT', MarketingGender::MEN);
        $linkNote($ch2, $grapefruit, 'TOP'); $linkNote($ch2, $cedar, 'BASE'); $linkNote($ch2, $vetiver, 'BASE');

        $ch3 = $perfumeOf($chanel, 'Chance', 2003, 'EDP', MarketingGender::WOMEN);
        $linkNote($ch3, $lemon, 'TOP'); $linkNote($ch3, $jasmine, 'HEART'); $linkNote($ch3, $patchouli, 'BASE');

        $ch4 = $perfumeOf($chanel, 'No.5', 1921, 'PARFUM', MarketingGender::WOMEN);
        $linkNote($ch4, $aldehydes = $noteOf('Aldehydes','Aldehydic'), 'TOP');
        $linkNote($ch4, $rose, 'HEART'); $linkNote($ch4, $sandal, 'BASE');

        $ch5 = $perfumeOf($chanel, 'Allure Homme Sport', 2004, 'EDT', MarketingGender::MEN);
        $linkNote($ch5, $orange = $noteOf('Orange','Citrus'), 'TOP');
        $linkNote($ch5, $musk, 'BASE'); $linkNote($ch5, $cedar, 'BASE');

        // ===== Dior (5) — démo =====
        $d1 = $perfumeOf($dior, 'Sauvage', 2015, 'EDT', MarketingGender::MEN);
        $linkNote($d1, $bergamot, 'TOP'); $linkNote($d1, $lavender, 'HEART'); $linkNote($d1, $cedar, 'BASE');

        $d2 = $perfumeOf($dior, 'Miss Dior', 2017, 'EDP', MarketingGender::WOMEN);
        $linkNote($d2, $rose, 'HEART'); $linkNote($d2, $patchouli, 'BASE'); $linkNote($d2, $bergamot, 'TOP');

        $d3 = $perfumeOf($dior, 'Homme Intense', 2011, 'EDP', MarketingGender::MEN);
        $linkNote($d3, $iris, 'HEART'); $linkNote($d3, $cedar, 'BASE'); $linkNote($d3, $vetiver, 'BASE');

        $d4 = $perfumeOf($dior, 'Fahrenheit', 1988, 'EDT', MarketingGender::MEN);
        $linkNote($d4, $leather, 'HEART'); $linkNote($d4, $violet, 'HEART'); $linkNote($d4, $amber, 'BASE');

        $d5 = $perfumeOf($dior, 'J’adore', 1999, 'EDP', MarketingGender::WOMEN);
        $linkNote($d5, $jasmine, 'HEART'); $linkNote($d5, $rose, 'HEART'); $linkNote($d5, $musk, 'BASE');

        // ===== Tom Ford (5) — démo =====
        $t1 = $perfumeOf($tomford, 'Oud Wood', 2007, 'EDP', MarketingGender::UNISEX);
        $linkNote($t1, $oud, 'BASE'); $linkNote($t1, $sandal, 'BASE'); $linkNote($t1, $vanilla, 'BASE');

        $t2 = $perfumeOf($tomford, 'Tobacco Vanille', 2007, 'EDP', MarketingGender::UNISEX);
        $linkNote($t2, $vanilla, 'BASE'); $linkNote($t2, $amber, 'BASE'); $linkNote($t2, $spices, 'TOP');

        $t3 = $perfumeOf($tomford, 'Black Orchid', 2006, 'EDP', MarketingGender::UNISEX);
        $linkNote($t3, $jasmine, 'HEART'); $linkNote($t3, $patchouli, 'BASE'); $linkNote($t3, $musk, 'BASE');

        $t4 = $perfumeOf($tomford, 'Neroli Portofino', 2011, 'EDT', MarketingGender::UNISEX);
        $linkNote($t4, $bergamot, 'TOP'); $linkNote($t4, $orange ??= $noteOf('Orange','Citrus'), 'TOP'); $linkNote($t4, $musk, 'BASE');

        $t5 = $perfumeOf($tomford, 'Noir Extreme', 2015, 'EDP', MarketingGender::MEN);
        $linkNote($t5, $spices, 'TOP'); $linkNote($t5, $amber, 'BASE'); $linkNote($t5, $vanilla, 'BASE');

        $em->flush();

        $io->success('Seed full-demo OK : 5 marques, >=5 parfums chacune, Caesar (Maison Cipro) avec notes officielles.');
        return Command::SUCCESS;
    }
}
