<?php
// tests/Service/PerfumeMatcherTest.php
namespace App\Tests\Service;

use App\Entity\Perfume;
use App\Entity\Brand;
use App\Entity\UserProfile;
use App\Service\PerfumeMatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PerfumeMatcherTest extends KernelTestCase
{
    public function testRecommendForNonUserRuns(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var PerfumeMatcher $matcher */
        $matcher = $container->get(PerfumeMatcher::class);

        $input = [
            'preferred_notes'   => ['Vanilla'],
            'preferred_accords' => ['Gourmand'],
            'budget_max'        => 100,
        ];

        $results = $matcher->recommendForNonUser($input, 5);

        $this->assertIsArray($results);
        if (!empty($results)) {
            $this->assertArrayHasKey('perfume', $results[0]);
            $this->assertArrayHasKey('score', $results[0]);
            $this->assertArrayHasKey('reasons', $results[0]);
        }
    }
}
