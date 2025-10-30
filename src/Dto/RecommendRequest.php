<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validate')]
class RecommendRequest
{
    public ?int $userId = null;     // option 1
    public ?string $user = null;    // option 2 (name)

    #[Assert\NotNull(message: 'limit is required')]
    #[Assert\Positive(message: 'limit must be > 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'limit must be <= 100')]
    public int $limit = 20;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        if (array_key_exists('userId', $data)) { $dto->userId = (int) $data['userId']; }
        if (array_key_exists('user', $data))   { $dto->user   = trim((string) $data['user']); }
        if (array_key_exists('limit', $data))  { $dto->limit  = (int) $data['limit']; }
        return $dto;
    }

    // Validation personnalisée : EITHER userId OR user (mais pas les deux)
    public function validate(ExecutionContextInterface $ctx): void
    {
        $hasId   = !empty($this->userId);
        $hasName = $this->user !== null && $this->user !== '';

        if (!$hasId && !$hasName) {
            $ctx->buildViolation('Provide "userId" OR "user"')
                ->atPath('user')
                ->addViolation();
        }

        if ($hasId && $hasName) {
            $ctx->buildViolation('Provide EITHER "userId" OR "user", not both')
                ->atPath('user')
                ->addViolation();
        }
    }
}
