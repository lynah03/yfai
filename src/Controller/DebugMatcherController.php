<?php

namespace App\Controller;

use App\Entity\UserProfile;
use App\Service\PerfumeMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DebugMatcherController extends AbstractController
{
    #[Route('/_debug/match/user/{id}', name: 'debug_match_user')]
    public function matchForUser(
        int $id,
        EntityManagerInterface $em,
        PerfumeMatcher $matcher
    ): Response {
        $user = $em->getRepository(UserProfile::class)->find($id);
        if (!$user) {
            return new Response('User not found', 404);
        }

        $results = $matcher->rankForUser($user, 10);
        $out = "Matches for user #{$user->getId()} ({$user->getName()}):\n\n";

        foreach ($results as $r) {
            $p = $r['perfume'];
            $out .= sprintf(
                "%s (%s) — score %.2f\n  %s\n\n",
                $p->getName(),
                $p->getBrand()?->getName(),
                $r['score'],
                implode(' | ', $r['reasons'])
            );
        }

        return new Response(nl2br($out));
    }

    #[Route('/_debug/match/non-user', name: 'debug_match_non_user')]
    public function matchForNonUser(PerfumeMatcher $matcher): Response
    {
        $input = [
            'preferred_notes'   => ['Vanilla', 'Tonka Bean'],
            'preferred_accords' => ['GOURMAND', 'AMBERY'],
            'concentration'     => 'EDP',
            'budget_max'        => 300,
        ];

        $results = $matcher->recommendForNonUser($input, 10);
        $out = "Matches for non-user quiz:\n\n";

        foreach ($results as $r) {
            $p = $r['perfume'];
            $out .= sprintf(
                "%s (%s) — score %.2f\n  %s\n\n",
                $p->getName(),
                $p->getBrand()?->getName(),
                $r['score'],
                implode(' | ', $r['reasons'])
            );
        }

        return new Response(nl2br($out));
    }
}
