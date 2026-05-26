<?php

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\PartnerApiKey;
use App\Repository\PartnerApiKeyRepository;
use App\Service\PartnerApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PartnerApiKeyManagerTest extends KernelTestCase
{
    public function testGenerateVerifyAndRevokePartnerApiKey(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $brand = (new Brand())->setName('API Key Atelier');
        $otherBrand = (new Brand())->setName('Other API Key Atelier');

        $entityManager->persist($brand);
        $entityManager->persist($otherBrand);
        $entityManager->flush();

        $repository = $entityManager->getRepository(PartnerApiKey::class);
        $this->assertInstanceOf(PartnerApiKeyRepository::class, $repository);

        $manager = new PartnerApiKeyManager($entityManager, $repository);

        $generation = $manager->generateForBrand($brand, 'Server integration');

        $this->assertArrayHasKey('plainKey', $generation);
        $this->assertArrayHasKey('apiKey', $generation);
        $this->assertIsString($generation['plainKey']);
        $this->assertStringStartsWith('yfai_live_', $generation['plainKey']);
        $this->assertInstanceOf(PartnerApiKey::class, $generation['apiKey']);

        /** @var PartnerApiKey $apiKey */
        $apiKey = $generation['apiKey'];
        $plainKey = $generation['plainKey'];

        $this->assertSame($brand->getId(), $apiKey->getBrand()?->getId());
        $this->assertSame('Server integration', $apiKey->getLabel());
        $this->assertStringContainsString('_'.$apiKey->getKeyPrefix().'_', $plainKey);
        $this->assertNotSame($plainKey, $apiKey->getKeyHash());
        $this->assertStringNotContainsString($plainKey, $apiKey->getKeyHash());
        $this->assertNotNull(password_get_info($apiKey->getKeyHash())['algo']);

        $this->assertNull($manager->verifyForBrand($otherBrand, $plainKey));
        $this->assertNull($manager->verifyForBrand($brand, 'yfai_live_badprefix_badsecret'));

        $verified = $manager->verifyForBrand($brand, $plainKey);

        $this->assertInstanceOf(PartnerApiKey::class, $verified);
        $this->assertSame($apiKey->getId(), $verified->getId());
        $this->assertNotNull($verified->getLastUsedAt());

        $manager->revoke($apiKey);

        $this->assertTrue($apiKey->isRevoked());
        $this->assertNull($manager->verifyForBrand($brand, $plainKey));
    }
}
