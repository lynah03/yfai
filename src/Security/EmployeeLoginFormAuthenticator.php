<?php

    namespace App\Security;

 
    use App\Entity\Employee;
    use App\Repository\EmployeeRepository;
    use Doctrine\ORM\EntityManagerInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Bundle\SecurityBundle\Security;
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

    class EmployeeLoginFormAuthenticator extends AbstractLoginFormAuthenticator
    {
        use TargetPathTrait;

        public function __construct(
            private EmployeeRepository          $employeeRepository,
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
            return $request->getPathInfo() === '/dashboard/login' && $request->isMethod('POST');
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
                    $user = $this->employeeRepository->findOneBy(['email' => $userIdentifier]);
                    if (!$user) {
                        throw new UserNotFoundException();
                    }
                    if (!$user instanceof Employee) {
                        throw new \LogicException('You are not allowed to connect here');
                    }
                    if ($user->getIsActive() === false) {
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
            $employee = $token->getUser();
            if (!$employee instanceof Employee) {
                throw new \LogicException('You are not allowed to connect here');
            }
            $employee->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->persist($employee);
            $this->entityManager->flush();

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
            return new RedirectResponse($this->router->generate('app_dashboard_login'));
        }

        public function start(Request $request, AuthenticationException $authException = null): Response
        {
            return new RedirectResponse($this->router->generate('app_dashboard_login'));
        }

        protected function getLoginUrl(Request $request): string
        {
            return $this->router->generate('app_dashboard_login');
        }

        protected function getDefaultSuccessRedirectUrl($user): string
        {
            // Role-based and job-based redirection logic.
            if (in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
                return $this->router->generate('app_tessam_dashboard_home_index');
            }

            /*
             * switch ($user->getJob()) {
                case EmployeeJob::SHOP_SALES:
                case EmployeeJob::SHOP_MANAGER:
                    $shops = $user->getShopsEmployedIn();
                    if (count($shops) === 1) {
                        return $this->router->generate('app_dashboard_crm_shops_pos_show_menu', ['id' => $shops[0]->getId()]);
                    } else {
                        return $this->router->generate('app_dashboard_crm_shops_index');
                    }
                case EmployeeJob::SALES_PERSON:
                    return $this->router->generate('app_dashboard_crm_customer_order_index');
                case EmployeeJob::SALES_MANAGER:
                    return $this->router->generate('app_dashboard_crm_index');
                case EmployeeJob::WAREHOUSE_MANAGER:
                    return $this->router->generate('app_dashboard_logistics_index');
                case EmployeeJob::WAREHOUSE_STAFF:
                    return $this->router->generate('app_dashboard_logistics_stocks_index');
                case EmployeeJob::ACCOUNTANT:
                    return $this->router->generate('app_dashboard_accounting_index');
                case EmployeeJob::HR_MANAGER:
                    return $this->router->generate('app_dashboard_hr_index');
                case EmployeeJob::IT_MANAGER:
                case EmployeeJob::IT_SUPPORT:
                    return $this->router->generate('app_dashboard_it_index');
                case EmployeeJob::MARKETING_AGENT:
                case EmployeeJob::MARKETING_MANAGER:
                    return $this->router->generate('app_dashboard_marketing_index');
                case EmployeeJob::TECHNICAL_SUPPORT:
                    return $this->router->generate('app_dashboard_technical_index');
                case EmployeeJob::DRIVER:
                    return $this->router->generate('app_dashboard_drivers_index');
                case EmployeeJob::SECURITY:
                    return $this->router->generate('app_dashboard_security_index');
                case EmployeeJob::SUPER_ADMIN:
                case EmployeeJob::GENERAL_MANAGER:
                case EmployeeJob::ADMINISTRATION_MANAGER:
                    return $this->router->generate('app_dashboard_home_index');
            }
             */
            return $this->router->generate('app_dashboard_home_index');
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
