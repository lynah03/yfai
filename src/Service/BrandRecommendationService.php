<?php

namespace App\Service;

use App\Entity\Brand;
use App\Enum\Concentration;
use App\Enum\MarketingGender;

final class BrandRecommendationService
{
    public function __construct(
        private readonly PerfumeMatcher $matcher,
    ) {
    }

    /**
     * @return array{
     *     count:int,
     *     totalAvailable:int,
     *     limit:int,
     *     offset:int,
     *     maxReasons:int,
     *     results:array<int,array<string,mixed>>
     * }
     */
    public function recommendForBrand(Brand $brand, array $input): array
    {
        $limit = isset($input['limit']) ? (int) $input['limit'] : 5;
        $offset = isset($input['offset']) ? (int) $input['offset'] : 0;
        $maxReasons = isset($input['maxReasons']) ? (int) $input['maxReasons'] : 8;

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $maxReasons = max(0, min(20, $maxReasons));

        /*
         * V1 behavior:
         * Score the catalog, then keep only perfumes belonging to the requested brand.
         *
         * Later, for performance, we can push this filter directly into the matcher/repository.
         */
        $ranked = $this->matcher->recommendForNonUser(
            input: $input,
            limit: 500,
            offset: 0,
            maxReasons: $maxReasons
        );

        $brandResults = array_values(array_filter(
            $ranked,
            static function (array $row) use ($brand): bool {
                $perfume = $row['perfume'];

                return $perfume->getBrand()?->getId() === $brand->getId();
            }
        ));

        $totalAvailable = count($brandResults);

        $brandResults = array_slice($brandResults, $offset, $limit);

        $results = array_map(function (array $row): array {
            $perfume = $row['perfume'];

            $concentration = $perfume->getConcentration();
            $marketingGender = $perfume->getMarketingGender();
            $image = $perfume->getImage();

            return [
                'perfumeId' => $perfume->getId(),
                'brandId' => $perfume->getBrand()?->getId(),
                'brand' => $perfume->getBrand()?->getName(),

                'name' => $perfume->getName(),
                'shortDescription' => $perfume->getShortDescription(),
                'description' => $perfume->getDescription(),

                'image' => $image,
                'imageUrl' => $this->resolveImageUrl($image),
                'productUrl' => $perfume->getProductUrl(),

                'concentration' => $concentration instanceof Concentration ? $concentration->value : null,
                'marketingGender' => $marketingGender instanceof MarketingGender ? $marketingGender->value : null,

                'listPriceCents' => $perfume->getListPriceCents(),
                'listPriceCurrency' => $perfume->getListPriceCurrency(),

                'score' => $row['score'],
                'reasons' => $row['reasons'] ?? [],
            ];
        }, $brandResults);

        return [
            'count' => count($results),
            'totalAvailable' => $totalAvailable,
            'limit' => $limit,
            'offset' => $offset,
            'maxReasons' => $maxReasons,
            'results' => $results,
        ];
    }

    private function resolveImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/')) {
            return $image;
        }

        return '/uploads/perfumes/'.$image;
    }
}