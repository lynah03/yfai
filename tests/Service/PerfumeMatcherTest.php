<?php
// tests/Service/PerfumeMatcherTest.php
namespace App\Tests\Service;

use App\Entity\Perfume;
use App\Entity\Brand;
use App\Entity\UserProfile;
use App\Service\PerfumeMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PerfumeMatcherTest extends KernelTestCase
{
    public function testRecommendForNonUserRuns(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var PerfumeMatcher $matcher */
        $matcher = $container->get(PerfumeMatcher::class);

        $input = [
            'preferred_notes'   => ['Vanilla'],
            'preferred_accords' => ['Gourmand'],
            'budget_max'        => 100,
        ];

        $results = $matcher->recommendForNonUser($input, 5);

        $this->assertIsArray($results);
        if (!empty($results)) {
            $this->assertArrayHasKey('perfume', $results[0]);
            $this->assertArrayHasKey('score', $results[0]);
            $this->assertArrayHasKey('reasons', $results[0]);
        }
    }

    public function testRecommendForBrandNonUserOnlyRanksBrandCatalog(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $targetBrand = (new Brand())->setName('Target House');
        $otherBrand = (new Brand())->setName('Other House');

        $targetPerfume = (new Perfume())
            ->setBrand($targetBrand)
            ->setName('Target Signature');

        $otherPerfume = (new Perfume())
            ->setBrand($otherBrand)
            ->setName('Other Signature');

        $entityManager->persist($targetBrand);
        $entityManager->persist($otherBrand);
        $entityManager->persist($targetPerfume);
        $entityManager->persist($otherPerfume);
        $entityManager->flush();

        /** @var PerfumeMatcher $matcher */
        $matcher = $container->get(PerfumeMatcher::class);

        $results = $matcher->recommendForBrandNonUser($targetBrand, [], 10);

        $this->assertCount(1, $results);
        $this->assertSame($targetPerfume->getId(), $results[0]['perfume']->getId());
        $this->assertSame($targetBrand->getId(), $results[0]['perfume']->getBrand()?->getId());
    }
}
