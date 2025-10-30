<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class GetRecommendationsRequest
{
    #[Assert\NotNull(message: 'limit is required')]
    #[Assert\Positive(message: 'limit must be > 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'limit must be <= 100')]
    public int $limit = 20;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'offset must be >= 0')]
    public int $offset = 0;

    public static function fromQueryBag(\Symfony\Component\HttpFoundation\ParameterBag $q): self
    {
        $dto = new self();
        if ($q->has('limit'))  { $dto->limit  = (int) $q->get('limit'); }
        if ($q->has('offset')) { $dto->offset = (int) $q->get('offset'); }
        return $dto;
    }
}
