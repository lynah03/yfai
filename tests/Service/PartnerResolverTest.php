<?php

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Service\PartnerResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PartnerResolverTest extends KernelTestCase
{
    public function testResolveSupportsExactAndCaseInsensitiveBrandNames(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $brand = new Brand();
        $brand->setName('Roja Dove');
        $brand->setPartnerSlug('roja-dove');
        $brand->setCountry('United Kingdom');

        $entityManager->persist($brand);
        $entityManager->flush();

        /** @var PartnerResolver $resolver */
        $resolver = $container->get(PartnerResolver::class);

        $this->assertSame($brand->getId(), $resolver->resolve('roja-dove')?->getId());
        $this->assertSame($brand->getId(), $resolver->resolve('ROJA-DOVE')?->getId());
        $this->assertSame($brand->getId(), $resolver->resolve('Roja Dove')?->getId());
        $this->assertSame($brand->getId(), $resolver->resolve('roja dove')?->getId());
        $this->assertNull($resolver->resolve('Unknown House'));
    }
}
