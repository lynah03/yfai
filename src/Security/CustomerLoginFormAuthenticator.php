<?php

    namespace App\Security;


    //use App\Entity\CustomerUser;
    use App\Entity\Employee;
   // use App\Repository\CustomerUserRepository;
    use App\Entity\UserProfile;
    use App\Repository\EmployeeRepository;
    use App\Repository\UserProfileRepository;
    use Doctrine\ORM\EntityManagerInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\HttpFoundation\RedirectResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    use Symfony\Component\Routing\RouterInterface;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Exception\AuthenticationException;
    use Symfony\Component\Security\Core\Exception\UserNotFoundException;
    use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
    use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
    use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
    use Symfony\Component\Security\Http\SecurityRequestAttributes;
    use Symfony\Component\Security\Http\Util\TargetPathTrait;

    class CustomerLoginFormAuthenticator extends AbstractLoginFormAuthenticator
    {
        use TargetPathTrait;

        public function __construct(
            private UserProfileRepository          $customerUserRepository,
            private UserPasswordHasherInterface $passwordHasher,
            private RouterInterface             $router,
            private LoggerInterface             $logger,
            private JWTEncoderInterface         $jwtEncoder,
            private JWTTokenManagerInterface    $JWTManager,
            private readonly EntityManagerInterface $entityManager,
        ) {
        }

        public function supports(Request $request): bool
        {
            return $request->getPathInfo() === '/customer/login' && $request->isMethod('POST');
        }

        public function authenticate(Request $request): Passport
        {
            $email = $request->request->get('email', '');
            $password = $request->request->get('password', '');
            if ($email === '' || $password === '') {
                throw new AuthenticationException('Missing credentials');
            }

            return new Passport(
                new UserBadge($email, function ($userIdentifier) {
                    $user = $this->customerUserRepository->findOneBy(['email' => $userIdentifier]);
                    if (!$user) {
                        throw new UserNotFoundException();
                    }
                    if (!$user instanceof UserProfile) {
                        throw new \LogicException('You are not allowed to connect here');
                    }
                    if ($user->isVerified() === false) {
                        throw new AuthenticationException('Account is not active, please ask administrator to activate your account');
                    }
                    return $user;
                }),
                new PasswordCredentials($password),
                [
                    new CsrfTokenBadge('authenticate', $request->get('_csrf_token')),
                    (new RememberMeBadge())->enable(),
                ]
            );
        }

        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
        {
            $this->logger->info('User ' . $token->getUser()?->getEmail() . ' has been logged in');

            if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
                return new RedirectResponse($targetPath);
            }

            // Generate or retrieve a valid JWT token.
            // You might expose the token to your frontend via a session variable or an additional endpoint.
            $jwt = $this->getOrCreateJwtToken($token->getUser());
            $route = $this->getDefaultSuccessRedirectUrl($token->getUser());

            return new RedirectResponse($route);
        }

        public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
        {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
            return new RedirectResponse($this->router->generate('app_customer_login'));
        }

        public function start(Request $request, AuthenticationException $authException = null): Response
        {
            return new RedirectResponse($this->router->generate('app_customer_login'));
        }

        protected function getLoginUrl(Request $request): string
        {
            return $this->router->generate('app_customer_login');
        }

        protected function getDefaultSuccessRedirectUrl($user): string
        {
            // Role-based and job-based redirection logic.
            return $this->router->generate('app_customer_index');
        }

        public function getOrCreateJwtToken($user): string
        {
            $storedToken = $user->getApiToken();
            $isValid = false;
            if ($storedToken) {
                try {
                    $decoded = $this->jwtEncoder->decode($storedToken);
                    if (isset($decoded['exp']) && $decoded['exp'] > time()) {
                        $isValid = true;
                    }
                } catch (\Exception $e) {
                    $isValid = false;
                }
            }

            if (!$isValid) {
                $jwt = $this->JWTManager->create($user);
                // Store the token in plain text so it can be decoded later for validation.
                $user->setApiToken($jwt);
                $this->entityManager->flush();
            } else {
                $jwt = $storedToken;
            }

            return $jwt;
        }
    }
