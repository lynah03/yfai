<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PutBudgetPreferencesRequest
{
    #[Assert\PositiveOrZero(message: 'minCents must be >= 0')]
    public ?int $minCents = null;

    #[Assert\PositiveOrZero(message: 'maxCents must be >= 0')]
    public ?int $maxCents = null;

    #[Assert\Currency(message: 'Invalid ISO currency')]
    public ?string $currency = 'EUR';

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->minCents = isset($data['minCents']) ? (int)$data['minCents'] : null;
        $self->maxCents = isset($data['maxCents']) ? (int)$data['maxCents'] : null;
        $self->currency = isset($data['currency']) && $data['currency'] !== null
            ? strtoupper(trim((string)$data['currency']))
            : 'EUR';
        return $self;
    }
}
