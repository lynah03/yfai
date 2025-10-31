<?php

    namespace App\Controller\Api\Security;

    use App\Entity\Employee;
    use App\Entity\RefreshToken;
    use Doctrine\ORM\EntityManagerInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    use Symfony\Component\Routing\Attribute\Route;

    class ApiLoginController extends AbstractController
    {
        public function __construct(
            private EntityManagerInterface      $em,
            private UserPasswordHasherInterface $passwordHasher,
            private JWTTokenManagerInterface    $jwtManager,
            private JWTEncoderInterface         $jwtEncoder,
            private LoggerInterface             $logger,
        ) {}

        #[Route('/api/login', name: 'api_login', methods: ['POST'])]
        public function login(Request $request): JsonResponse
        {
            $data          = json_decode($request->getContent(), true);
            $email         = $data['email']    ?? '';
            $plainPassword = $data['password'] ?? '';
            $providedJwt   = $data['token']    ?? '';

            // 1) Load user by email
            /** @var Employee|null $user */
            $user = $this->em
                ->getRepository(Employee::class)
                ->findOneBy(['email' => $email]);


            if (!$user) {
                return new JsonResponse(['error' => 'Invalid credentials'], 401);
            }
            if (!$this->passwordHasher->isPasswordValid($user, $plainPassword)) {
                return new JsonResponse(['error' => 'Invalid credentials'], 401);
            }
            // 2) If no JWT is provided, verify password
            if (empty($providedJwt) && ! $this->passwordHasher->isPasswordValid($user, $plainPassword)) {
                return new JsonResponse(['error' => 'Invalid credentials'], 401);
            }

            // 3) Determine a valid access token
            $accessToken = null;
            if ($providedJwt) {
                try {
                    $decoded = $this->jwtEncoder->decode($providedJwt);
                    if (!empty($decoded['exp']) && $decoded['exp'] > time()) {
                        $accessToken = $providedJwt;
                    }
                } catch (\Throwable $e) {
                    // invalid or expired, will generate below
                }
            }
            if (!$accessToken) {
                $accessToken = $this->jwtManager->create($user);
            }
            $user->setApiToken($accessToken);
            // 4) Generate & persist a 30-day refresh token
            $refreshTokenString = bin2hex(random_bytes(32));
            $refreshToken = new RefreshToken();
            $refreshToken
                ->setUser($user)
                ->setToken($refreshTokenString)
                ->setExpiresAt(new \DateTime('+7 days'))
                ->setRevoked(false)
            ;

            $this->em->persist($refreshToken);
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->em->persist($user);
            $this->em->flush();

            // 5) Log the issuance
            $this->logger->info('API login: issued tokens', [
                'user_id'       => $user->getId(),
                'token'         => substr($accessToken, 0, 20) . '…',
                'refresh_token' => substr($refreshTokenString, 0, 20) . '…',
            ]);

            // 6) Gather the rest of the user info

            // 7) Return combined payload
            return new JsonResponse([
                'status'        => 'ok',
                'success'       => true,
                'token'         => $accessToken,
                'refresh_token' => $refreshTokenString,
                
            ]);
        }

       

        #[Route('/api/check', name: 'api_check', methods: ['GET'])]
        public function check(): JsonResponse
        {
            return new JsonResponse(['status'=>'ok','success'=>true]);
        }
    }
