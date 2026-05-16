<?php

namespace App\Command;

use App\Entity\Brand;
use App\Entity\Note;
use App\Entity\Perfume;
use App\Entity\PerfumeNote;
use App\Enum\Concentration;
use App\Enum\MarketingGender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:roja-dove-perfumes',
    description: 'Seed 5 Roja Dove perfumes for AI Scent Concierge testing.'
)]
final class SeedRojaDovePerfumesCommand extends Command
{
    /** @var array<string, Note> */
    private array $noteCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $brand = $this->findOrCreateBrand();

        $perfumes = [
            [
                'name' => 'Elysium Pour Homme',
                'shortDescription' => 'Bright citrus, weightless musk and masculine refinement.',
                'description' => 'A luminous fougère built around grapefruit, citrus brightness and airy musks. Elegant, charismatic and easy to wear, it feels clean without losing its luxury presence.',
                'productUrl' => 'https://rojadoveperfumery.com/products/elysium-pour-homme-1',
                'priceCents' => 42500,
                'currency' => 'EUR',
                'concentration' => Concentration::PARFUM,
                'marketingGender' => MarketingGender::MEN,
                'notes' => [
                    ['name' => 'Grapefruit', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Bergamot', 'layer' => 'TOP', 'intensity' => 2],
                    ['name' => 'Citrus', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Musk', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Cedarwood', 'layer' => 'BASE', 'intensity' => 2],
                ],
            ],
            [
                'name' => 'Enigma Pour Homme',
                'shortDescription' => 'Cognac, tobacco and vanilla wrapped in smoky elegance.',
                'description' => 'A rich amber composition with a boozy cognac effect, smoky tobacco, vanilla warmth and polished spice. Seductive, mysterious and dressed like a private members’ club after midnight.',
                'productUrl' => 'https://rojadoveperfumery.com/products/enigma-parfum-pour-homme',
                'priceCents' => 42000,
                'currency' => 'EUR',
                'concentration' => Concentration::PARFUM,
                'marketingGender' => MarketingGender::MEN,
                'notes' => [
                    ['name' => 'Cognac', 'layer' => 'HEART', 'intensity' => 3],
                    ['name' => 'Tobacco', 'layer' => 'BASE', 'intensity' => 3],
                    ['name' => 'Vanilla', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Benzoin', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Cardamom', 'layer' => 'TOP', 'intensity' => 2],
                    ['name' => 'Ginger', 'layer' => 'TOP', 'intensity' => 2],
                    ['name' => 'Pepper', 'layer' => 'TOP', 'intensity' => 2],
                ],
            ],
            [
                'name' => 'Amber Aoud',
                'shortDescription' => 'Rose, saffron and oud in a rich amber spell.',
                'description' => 'A lavish amber-oud fragrance where rose, saffron, fig and benzoin melt into a warm, sensual base. Opulent, soft, spicy and unmistakably addictive.',
                'productUrl' => 'https://rojadoveperfumery.com/products/amber-aoud-100ml',
                'priceCents' => 61000,
                'currency' => 'EUR',
                'concentration' => Concentration::PARFUM,
                'marketingGender' => MarketingGender::UNISEX,
                'notes' => [
                    ['name' => 'Rose', 'layer' => 'HEART', 'intensity' => 3],
                    ['name' => 'Fig', 'layer' => 'HEART', 'intensity' => 2],
                    ['name' => 'Saffron', 'layer' => 'BASE', 'intensity' => 3],
                    ['name' => 'Oud', 'layer' => 'BASE', 'intensity' => 3],
                    ['name' => 'Benzoin', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Cinnamon', 'layer' => 'BASE', 'intensity' => 2],
                ],
            ],
            [
                'name' => 'Oceania Parfum',
                'shortDescription' => 'A crisp sea breeze over moss, sandalwood and orris.',
                'description' => 'A radiant fresh composition inspired by ocean air and sunlit skin. Citrus, aromatic herbs, moss, sandalwood and orris create a clean but luxurious trail.',
                'productUrl' => 'https://rojadoveperfumery.com/products/oceania-parfum',
                'priceCents' => 38500,
                'currency' => 'EUR',
                'concentration' => Concentration::PARFUM,
                'marketingGender' => MarketingGender::UNISEX,
                'notes' => [
                    ['name' => 'Bergamot', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Lime', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Mandarin', 'layer' => 'TOP', 'intensity' => 2],
                    ['name' => 'Geranium', 'layer' => 'HEART', 'intensity' => 2],
                    ['name' => 'Moss', 'layer' => 'BASE', 'intensity' => 3],
                    ['name' => 'Sandalwood', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Orris', 'layer' => 'BASE', 'intensity' => 2],
                ],
            ],
            [
                'name' => 'Burlington 1819',
                'shortDescription' => 'Citrus brightness, rum, tobacco and British opulence.',
                'description' => 'A rich and sparkling fragrance inspired by Burlington Arcade. Grapefruit, lime and mint open brightly before ginger, saffron, oakmoss, rum and tobacco create a polished, luxurious depth.',
                'productUrl' => 'https://rojadoveperfumery.com/products/burlington-1819',
                'priceCents' => 34500,
                'currency' => 'EUR',
                'concentration' => Concentration::PARFUM,
                'marketingGender' => MarketingGender::UNISEX,
                'notes' => [
                    ['name' => 'Grapefruit', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Lime', 'layer' => 'TOP', 'intensity' => 3],
                    ['name' => 'Mint', 'layer' => 'TOP', 'intensity' => 2],
                    ['name' => 'Ginger', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Saffron', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Oakmoss', 'layer' => 'BASE', 'intensity' => 3],
                    ['name' => 'Rum', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Tobacco', 'layer' => 'BASE', 'intensity' => 2],
                    ['name' => 'Benzoin', 'layer' => 'BASE', 'intensity' => 2],
                ],
            ],
        ];

        foreach ($perfumes as $data) {
            $perfume = $this->findOrCreatePerfume($brand, $data['name']);

            $perfume
                ->setBrand($brand)
                ->setName($data['name'])
                ->setShortDescription($data['shortDescription'])
                ->setDescription($data['description'])
                ->setProductUrl($data['productUrl'])
                ->setListPriceCents($data['priceCents'])
                ->setListPriceCurrency($data['currency'])
                ->setConcentration($data['concentration'])
                ->setMarketingGender($data['marketingGender']);

            $this->replacePerfumeNotes($perfume, $data['notes']);

            $this->em->persist($perfume);

            $output->writeln(sprintf('Seeded/updated: %s', $data['name']));
        }

        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>Roja Dove perfumes seeded successfully.</info>');
        $output->writeln('Images are intentionally empty: add them from the dashboard.');

        return Command::SUCCESS;
    }

    private function findOrCreateBrand(): Brand
    {
        $brand = $this->em
            ->getRepository(Brand::class)
            ->findOneBy(['name' => 'Roja Dove']);

        if (!$brand) {
            $brand = new Brand();
            $brand
                ->setName('Roja Dove')
                ->setCountry('United Kingdom')
                ->setDescription('A British luxury fragrance house known for opulent compositions, high-impact materials and a couture approach to perfumery.');

            $this->em->persist($brand);
        }

        return $brand;
    }

    private function findOrCreatePerfume(Brand $brand, string $name): Perfume
    {
        $perfume = $this->em
            ->getRepository(Perfume::class)
            ->findOneBy([
                'brand' => $brand,
                'name' => $name,
            ]);

        if ($perfume) {
            return $perfume;
        }

        $perfume = new Perfume();
        $perfume->setBrand($brand);
        $perfume->setName($name);

        return $perfume;
    }

    /**
     * @param array<int,array{name:string,layer:string,intensity:int}> $notes
     */
    private function replacePerfumeNotes(Perfume $perfume, array $notes): void
    {
        foreach ($perfume->getPerfumeNotes()->toArray() as $existingPerfumeNote) {
            $perfume->removePerfumeNote($existingPerfumeNote);
            $this->em->remove($existingPerfumeNote);
        }

        foreach ($notes as $noteData) {
            $note = $this->findOrCreateNote($noteData['name']);

            $perfumeNote = new PerfumeNote();
            $perfumeNote
                ->setPerfume($perfume)
                ->setNote($note)
                ->setLayer($noteData['layer'])
                ->setIntensity($noteData['intensity']);

            $perfume->addPerfumeNote($perfumeNote);
            $this->em->persist($perfumeNote);
        }
    }

    private function findOrCreateNote(string $name): Note
    {
        $name = trim($name);
        $cacheKey = mb_strtolower($name);

        if (isset($this->noteCache[$cacheKey])) {
            return $this->noteCache[$cacheKey];
        }

        $note = $this->em
            ->getRepository(Note::class)
            ->findOneBy(['name' => $name]);

        if (!$note) {
            $note = new Note();
            $note->setName($name);
            $this->em->persist($note);
        }

        $this->noteCache[$cacheKey] = $note;

        return $note;
    }
}
