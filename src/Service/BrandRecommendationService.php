<?php

namespace App\Service;

use App\Entity\Brand;

final class BrandRecommendationService
{
    public function __construct(
        private readonly PerfumeMatcher $matcher,
        private readonly RecommendationResultPresenter $resultPresenter,
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

        $results = array_map(
            fn (array $row): array => $this->resultPresenter->presentConciergeResult($row, $input),
            $brandResults
        );

        return [
            'count' => count($results),
            'totalAvailable' => $totalAvailable,
            'limit' => $limit,
            'offset' => $offset,
            'maxReasons' => $maxReasons,
            'results' => $results,
        ];
    }
}
