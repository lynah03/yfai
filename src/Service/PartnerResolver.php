<?php

namespace App\Service;

use App\Entity\Brand;
use App\Repository\BrandRepository;

final class PartnerResolver
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {
    }

    public function resolve(string $identifier): ?Brand
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $brand = $this->brandRepository->findOneBy(['name' => $identifier]);

        if ($brand instanceof Brand) {
            return $brand;
        }

        // TODO: add slug and partner alias resolution when partner identifiers move beyond exact names.
        return $this->brandRepository->findOneByNameCI($identifier);
    }
}
