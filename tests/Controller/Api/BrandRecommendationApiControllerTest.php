<?php

namespace App\Tests\Controller\Api;

use App\Entity\Brand;
use App\Entity\PartnerApiKey;
use App\Entity\Perfume;
use App\Repository\PartnerApiKeyRepository;
use App\Service\PartnerApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BrandRecommendationApiControllerTest extends WebTestCase
{
    public function testValidBearerPartnerKeyAllowsRecommendation(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        [$brand, $plainKey] = $this->createBrandWithKey($container->get(EntityManagerInterface::class), 'Bearer');

        $client->request(
            'POST',
            sprintf('/api/partners/%s/recommendations', $brand->getPartnerSlug()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$plainKey,
            ],
            json_encode(['limit' => 5], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse($client->getResponse());

        $this->assertSame([
            'ok',
            'customer',
            'count',
            'totalAvailable',
            'limit',
            'offset',
            'maxReasons',
            'results',
        ], array_keys($payload));
        $this->assertTrue($payload['ok']);
        $this->assertSame($brand->getId(), $payload['customer']['id']);
        $this->assertSame(1, $payload['count']);
        $this->assertArrayNotHasKey('apiKey', $payload);
        $this->assertArrayNotHasKey('plainKey', $payload);
    }

    public function testValidPartnerKeyHeaderAllowsRecommendation(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        [$brand, $plainKey] = $this->createBrandWithKey($container->get(EntityManagerInterface::class), 'Header');

        $client->request(
            'POST',
            sprintf('/api/partners/%s/recommendations', $brand->getPartnerSlug()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_YFAI_PARTNER_KEY' => $plainKey,
            ],
            json_encode(['limit' => 5], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse($client->getResponse());

        $this->assertTrue($payload['ok']);
        $this->assertSame($brand->getId(), $payload['customer']['id']);
    }

    public function testMissingPartnerKeyReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        [$brand] = $this->createBrandWithKey($container->get(EntityManagerInterface::class), 'Missing');

        $client->request(
            'POST',
            sprintf('/api/partners/%s/recommendations', $brand->getPartnerSlug()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['limit' => 5], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $payload = $this->decodeResponse($client->getResponse());

        $this->assertFalse($payload['ok']);
        $this->assertSame('invalid_partner_key', $payload['error']);
    }

    public function testWrongBrandAndRevokedPartnerKeysReturnUnauthorized(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        [$brand, $plainKey, $apiKey, $manager] = $this->createBrandWithKey($entityManager, 'Revoked');
        [$otherBrand, $otherPlainKey] = $this->createBrandWithKey($entityManager, 'Other');

        $client->request(
            'POST',
            sprintf('/api/partners/%s/recommendations', $brand->getPartnerSlug()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_YFAI_PARTNER_KEY' => $otherPlainKey,
            ],
            json_encode(['limit' => 5], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $manager->revoke($apiKey);

        $client->request(
            'POST',
            sprintf('/api/partners/%s/recommendations', $brand->getPartnerSlug()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_YFAI_PARTNER_KEY' => $plainKey,
            ],
            json_encode(['limit' => 5], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertNotSame($brand->getId(), $otherBrand->getId());
    }

    /**
     * @return array{0:Brand,1:string,2:PartnerApiKey,3:PartnerApiKeyManager}
     */
    private function createBrandWithKey(EntityManagerInterface $entityManager, string $suffix): array
    {
        $unique = strtolower(str_replace('.', '', uniqid($suffix.'-', true)));

        $brand = (new Brand())
            ->setName('Partner API '.$suffix.' '.$unique)
            ->setPartnerSlug('partner-api-'.$unique);

        $perfume = (new Perfume())
            ->setBrand($brand)
            ->setName('Signature '.$unique);

        $entityManager->persist($brand);
        $entityManager->persist($perfume);
        $entityManager->flush();

        $repository = $entityManager->getRepository(PartnerApiKey::class);
        $this->assertInstanceOf(PartnerApiKeyRepository::class, $repository);

        $manager = new PartnerApiKeyManager($entityManager, $repository);
        $generation = $manager->generateForBrand($brand, 'Test key');

        return [$brand, $generation['plainKey'], $generation['apiKey'], $manager];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponse(Response $response): array
    {
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }
}
