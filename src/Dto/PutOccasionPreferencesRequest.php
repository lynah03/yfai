<?php
namespace App\Dto;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints as Assert;

class PutOccasionPreferencesRequest
{
    /**
     * @var array<int,array{occasion:string,weight:int}>
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\All(
        new Assert\Collection(
            fields: [
                'occasion' => new Assert\Required([new Assert\Type('string'), new Assert\NotBlank()]),
                'weight'   => new Assert\Required([new Assert\Type('integer'), new Assert\Range(min: 0, max: 5)]),
            ],
            allowExtraFields: false
        )
    )]
    public array $items = [];

    public static function fromArray(array $data): self {
        $dto = new self();
        $dto->items = (array)($data['items'] ?? []);
        return $dto;
    }

    public static function fromParameterBag(ParameterBag $bag): self {
        return self::fromArray($bag->all());
    }
}
