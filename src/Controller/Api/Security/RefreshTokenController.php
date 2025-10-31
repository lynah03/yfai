<?php
// src/Controller/Api/RefreshTokenController.php

    namespace App\Controller\Api\Security;

    use App\Entity\RefreshToken;
    use Doctrine\ORM\EntityManagerInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\Routing\Attribute\Route;

    class RefreshTokenController extends AbstractController
    {
        public function __construct(
            private EntityManagerInterface   $em,
            private JWTTokenManagerInterface $jwtManager,
            private readonly LoggerInterface $logger,
        ) {

        }

        #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]

        public function refresh(Request $request): JsonResponse
        {
            $this->logger->info('Received refresh token request', [
                'request' => $request->getContent(),
            ]);
            $data = json_decode($request->getContent(), true);
            $provided = $data['refresh_token'] ?? '';
            $this->logger->info('Provided refresh token', [
                'token' => $provided,
            ]);
            /** @var RefreshToken|null $stored */
            $stored = $this->em
                ->getRepository(RefreshToken::class)
                ->findOneByToken($provided);
            $this->logger->info('Stored refresh token', [
                'stored' => $stored ? $stored->getToken() : null,
            ]);
            if (
                !$stored ||
                $stored->isRevoked() ||
                $stored->getExpiresAt() < new \DateTime()
            ) {
                return new JsonResponse(['error' => 'Invalid refresh token'], 401);
            }

            // Revoke the old token
            $stored->setRevoked(true);

            // Issue a new refresh token (rotate)
            $newToken = bin2hex(random_bytes(32));
            $newRt = new RefreshToken();
            $newRt
                ->setUser($stored->getUser())
                ->setToken($newToken)
                ->setExpiresAt(new \DateTime('+30 days'))
                ->setRevoked(false);

            $this->em->persist($newRt);
            $this->em->flush();

            // Issue a new access JWT
            $newJwt = $this->jwtManager->create($stored->getUser());
            $stored->getUser()->setApiToken($newJwt);
            return new JsonResponse([
                'token'         => $newJwt,
                'refresh_token' => $newToken,
            ]);
        }
    }
