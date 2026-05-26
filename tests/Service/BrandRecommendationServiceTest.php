<?php

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\Perfume;
use App\Service\BrandRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BrandRecommendationServiceTest extends KernelTestCase
{
    public function testRecommendForBrandKeepsEnvelopeAndExcludesOtherBrands(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $targetBrand = (new Brand())->setName('Service Target House');
        $otherBrand = (new Brand())->setName('Service Other House');

        $targetOne = (new Perfume())
            ->setBrand($targetBrand)
            ->setName('Service Target One');

        $targetTwo = (new Perfume())
            ->setBrand($targetBrand)
            ->setName('Service Target Two');

        $otherPerfume = (new Perfume())
            ->setBrand($otherBrand)
            ->setName('Service Other One');

        $entityManager->persist($targetBrand);
        $entityManager->persist($otherBrand);
        $entityManager->persist($targetOne);
        $entityManager->persist($targetTwo);
        $entityManager->persist($otherPerfume);
        $entityManager->flush();

        /** @var BrandRecommendationService $service */
        $service = $container->get(BrandRecommendationService::class);

        $response = $service->recommendForBrand($targetBrand, [
            'limit' => 10,
            'offset' => 0,
            'maxReasons' => 5,
        ]);

        $this->assertArrayHasKey('count', $response);
        $this->assertArrayHasKey('totalAvailable', $response);
        $this->assertArrayHasKey('limit', $response);
        $this->assertArrayHasKey('offset', $response);
        $this->assertArrayHasKey('maxReasons', $response);
        $this->assertArrayHasKey('results', $response);

        $this->assertSame(2, $response['count']);
        $this->assertSame(2, $response['totalAvailable']);
        $this->assertSame(10, $response['limit']);
        $this->assertSame(0, $response['offset']);
        $this->assertSame(5, $response['maxReasons']);

        $resultNames = array_column($response['results'], 'name');

        $this->assertContains('Service Target One', $resultNames);
        $this->assertContains('Service Target Two', $resultNames);
        $this->assertNotContains('Service Other One', $resultNames);

        foreach ($response['results'] as $result) {
            $this->assertSame($targetBrand->getId(), $result['brandId']);
            $this->assertSame($targetBrand->getName(), $result['brand']);
            $this->assertArrayHasKey('conciergeReason', $result);
            $this->assertArrayNotHasKey('reasons', $result);
        }
    }
}
