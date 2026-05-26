<?php

namespace App\Service;

use App\Entity\Brand;
use App\Entity\PartnerApiKey;
use App\Repository\PartnerApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PartnerApiKeyManager
{
    private const KEY_PART_1 = 'yfai';
    private const KEY_PART_2 = 'live';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PartnerApiKeyRepository $partnerApiKeyRepository,
    ) {
    }

    /**
     * Generates a partner API key and returns the plaintext key exactly once.
     *
     * @return array{apiKey:PartnerApiKey,plainKey:string}
     */
    public function generateForBrand(Brand $brand, ?string $label = null): array
    {
        $prefix = $this->generateUniquePrefix();
        $plainKey = sprintf(
            '%s_%s_%s_%s',
            self::KEY_PART_1,
            self::KEY_PART_2,
            $prefix,
            bin2hex(random_bytes(32))
        );
        $keyHash = password_hash($plainKey, PASSWORD_DEFAULT);

        if ($keyHash === false) {
            throw new \RuntimeException('Unable to hash partner API key.');
        }

        $apiKey = (new PartnerApiKey())
            ->setBrand($brand)
            ->setKeyPrefix($prefix)
            ->setKeyHash($keyHash)
            ->setLabel($label);

        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        return [
            'apiKey' => $apiKey,
            'plainKey' => $plainKey,
        ];
    }

    public function verifyForBrand(Brand $brand, string $plainKey): ?PartnerApiKey
    {
        $prefix = $this->extractPrefix($plainKey);

        if ($prefix === null) {
            return null;
        }

        $apiKey = $this->partnerApiKeyRepository->findActiveByBrandAndPrefix($brand, $prefix);

        if (!$apiKey instanceof PartnerApiKey) {
            return null;
        }

        if (!password_verify($plainKey, $apiKey->getKeyHash())) {
            return null;
        }

        $apiKey->markUsed();
        $this->entityManager->flush();

        return $apiKey;
    }

    public function revoke(PartnerApiKey $key): void
    {
        $key->revoke();
        $this->entityManager->flush();
    }

    private function generateUniquePrefix(): string
    {
        do {
            $prefix = bin2hex(random_bytes(4));
        } while ($this->partnerApiKeyRepository->findOneBy(['keyPrefix' => $prefix]) instanceof PartnerApiKey);

        return $prefix;
    }

    private function extractPrefix(string $plainKey): ?string
    {
        $parts = explode('_', trim($plainKey), 4);

        if (count($parts) !== 4) {
            return null;
        }

        [$partOne, $partTwo, $prefix, $secret] = $parts;

        if (!hash_equals(self::KEY_PART_1, $partOne) || !hash_equals(self::KEY_PART_2, $partTwo)) {
            return null;
        }

        if ($prefix === '' || $secret === '') {
            return null;
        }

        return $prefix;
    }
}
