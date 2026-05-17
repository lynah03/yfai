<?php

namespace App\Command;

use App\Entity\Accord;
use App\Entity\Brand;
use App\Entity\Perfume;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:scent-context',
    description: 'Seed olfactive accords and assign accords/seasons/occasions to demo perfumes.'
)]
final class SeedScentContextCommand extends Command
{
    /** @var array<string, Accord> */
    private array $accordCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->seedAccords($output);
        $this->assignPerfumeContext($output);

        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>Scent context seeded successfully.</info>');

        return Command::SUCCESS;
    }

    private function seedAccords(OutputInterface $output): void
    {
        $accords = [
            'AMBER' => ['Amber', 'Warm, resinous, enveloping and sensual.'],
            'WOODY' => ['Woody', 'Cedar, sandalwood, vetiver and polished woods.'],
            'OUD' => ['Oud', 'Dark, precious woods with deep resinous facets.'],
            'LEATHERY' => ['Leathery', 'Smooth leather, suede and smoky animalic warmth.'],
            'SPICY' => ['Spicy', 'Warm spices, saffron, pepper and aromatic heat.'],
            'MUSKY' => ['Musky', 'Clean skin, soft sensuality and intimate comfort.'],
            'FRESH' => ['Fresh', 'Bright, clean, airy and easy to wear.'],
            'CITRUS' => ['Citrus', 'Sparkling bergamot, lime, grapefruit and freshness.'],
            'FLORAL' => ['Floral', 'Elegant flowers, soft petals and refined femininity.'],
            'ROSE' => ['Rose', 'Velvet rose, romantic depth and modern elegance.'],
            'GOURMAND' => ['Gourmand', 'Vanilla, sweetness, edible warmth and addiction.'],
            'AROMATIC' => ['Aromatic', 'Lavender, herbs, mint and refined freshness.'],
            'AQUATIC' => ['Aquatic', 'Marine freshness, ocean air and mineral clarity.'],
            'POWDERY' => ['Powdery', 'Iris, orris, soft makeup notes and elegance.'],
            'SMOKY' => ['Smoky', 'Incense, smoke, depth and mysterious texture.'],
            'TOBACCO' => ['Tobacco', 'Warm tobacco leaves, richness and evening depth.'],
            'VANILLA' => ['Vanilla', 'Creamy vanilla, ambered sweetness and softness.'],
            'GREEN' => ['Green', 'Leaves, stems, herbs and natural freshness.'],
        ];

        foreach ($accords as $code => [$label, $description]) {
            $accord = $this->findOrCreateAccord($code);
            $accord
                ->setCode($code)
                ->setLabel($label)
                ->setDescription($description);

            $this->em->persist($accord);
            $output->writeln(sprintf('Accord ready: %s', $code));
        }
    }

    private function assignPerfumeContext(OutputInterface $output): void
    {
        $contexts = [
            'Maison Cipro' => [
                'Nero' => [
                    'accords' => ['SMOKY', 'OUD', 'LEATHERY', 'SPICY', 'AMBER'],
                    'seasons' => ['FALL', 'WINTER'],
                    'occasions' => ['DATE', 'EVENING', 'FORMAL'],
                ],
                'Caesar' => [
                    'accords' => ['LEATHERY', 'WOODY', 'SPICY', 'AROMATIC'],
                    'seasons' => ['FALL', 'WINTER'],
                    'occasions' => ['WORK', 'EVENING', 'FORMAL'],
                ],
            ],

            'Roja Dove' => [
                'Elysium Pour Homme' => [
                    'accords' => ['CITRUS', 'FRESH', 'AROMATIC', 'MUSKY', 'WOODY'],
                    'seasons' => ['SPRING', 'SUMMER'],
                    'occasions' => ['CASUAL', 'WORK', 'SPORT'],
                ],
                'Enigma Pour Homme' => [
                    'accords' => ['TOBACCO', 'AMBER', 'SPICY', 'VANILLA', 'GOURMAND', 'WOODY'],
                    'seasons' => ['FALL', 'WINTER'],
                    'occasions' => ['DATE', 'EVENING', 'FORMAL'],
                ],
                'Amber Aoud' => [
                    'accords' => ['OUD', 'AMBER', 'ROSE', 'SPICY', 'GOURMAND'],
                    'seasons' => ['FALL', 'WINTER'],
                    'occasions' => ['DATE', 'EVENING', 'FORMAL'],
                ],
                'Oceania Parfum' => [
                    'accords' => ['AQUATIC', 'FRESH', 'CITRUS', 'AROMATIC', 'WOODY', 'POWDERY'],
                    'seasons' => ['SPRING', 'SUMMER'],
                    'occasions' => ['CASUAL', 'WORK', 'SPORT'],
                ],
                'Burlington 1819' => [
                    'accords' => ['CITRUS', 'AROMATIC', 'TOBACCO', 'SPICY', 'WOODY', 'FRESH'],
                    'seasons' => ['SPRING', 'SUMMER', 'FALL'],
                    'occasions' => ['WORK', 'EVENING', 'FORMAL'],
                ],
            ],
        ];

        foreach ($contexts as $brandName => $perfumes) {
            $brand = $this->em->getRepository(Brand::class)->findOneBy(['name' => $brandName]);

            if (!$brand) {
                $output->writeln(sprintf('<comment>Skipped brand not found: %s</comment>', $brandName));
                continue;
            }

            foreach ($perfumes as $perfumeName => $context) {
                $perfume = $this->em->getRepository(Perfume::class)->findOneBy([
                    'brand' => $brand,
                    'name' => $perfumeName,
                ]);

                if (!$perfume) {
                    $output->writeln(sprintf('<comment>Skipped perfume not found: %s — %s</comment>', $brandName, $perfumeName));
                    continue;
                }

                foreach ($context['accords'] as $accordCode) {
                    $accord = $this->findOrCreateAccord($accordCode);
                    $perfume->addAccord($accord);
                }

                $perfume->setSeasons($context['seasons']);
                $perfume->setOccasions($context['occasions']);

                $this->em->persist($perfume);

                $output->writeln(sprintf(
                    'Context assigned: %s — %s',
                    $brandName,
                    $perfumeName
                ));
            }
        }
    }

    private function findOrCreateAccord(string $code): Accord
    {
        $code = strtoupper(trim($code));
        $cacheKey = mb_strtolower($code);

        if (isset($this->accordCache[$cacheKey])) {
            return $this->accordCache[$cacheKey];
        }

        $accord = $this->em
            ->getRepository(Accord::class)
            ->findOneBy(['code' => $code]);

        if (!$accord) {
            $accord = $this->em
                ->getRepository(Accord::class)
                ->createQueryBuilder('a')
                ->where('LOWER(a.code) = :code')
                ->setParameter('code', mb_strtolower($code))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        if (!$accord) {
            $accord = new Accord();
            $accord->setCode($code);
            $accord->setLabel(ucwords(strtolower(str_replace('_', ' ', $code))));

            $this->em->persist($accord);
        }

        $this->accordCache[$cacheKey] = $accord;

        return $accord;
    }
}
