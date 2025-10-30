<?php
namespace App\Dto;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints as Assert;

class PutBrandPreferencesRequest
{
    /**
     * @var array<int,array{brand?:string,brandId?:int,weight:int}>
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\All(
        new Assert\Collection(
            fields: [
                'brand'   => new Assert\Optional([new Assert\Type('string'), new Assert\NotBlank()]),
                'brandId' => new Assert\Optional([new Assert\Type('integer')]),
                'weight'  => new Assert\Required([new Assert\Type('integer'), new Assert\Range(min: -5, max: 5)]),
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
