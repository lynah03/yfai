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

        $brand = $this->brandRepository->findOneByPartnerSlug($identifier);

        if ($brand instanceof Brand) {
            return $brand;
        }

        $brand = $this->brandRepository->findOneBy(['name' => $identifier]);

        if ($brand instanceof Brand) {
            return $brand;
        }

        $brand = $this->brandRepository->findOneByPartnerSlugCI($identifier);

        if ($brand instanceof Brand) {
            return $brand;
        }

        return $this->brandRepository->findOneByNameCI($identifier);
    }
}
