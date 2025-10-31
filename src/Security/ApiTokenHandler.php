<?php
    
    namespace App\Security;
    

    use Symfony\Component\Security\Core\Exception\BadCredentialsException;
    use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
    use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
    use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
    
    class ApiTokenHandler implements AccessTokenHandlerInterface
    {
        public function __construct()
        {
        }
        
        public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
        {
            //$token = $this->apiTokenRepository->findOneBy(['token' => $accessToken]);
            $token =$accessToken; // Assuming $accessToken is the token string, replace with actual token retrieval logic
            if (!$token) {
                throw new BadCredentialsException();
            }
            
            if (!$token->isValid()) {
                throw new CustomUserMessageAuthenticationException('Token expired');
            }
            
            return new UserBadge($token->getOwnedBy()->getUserIdentifier());
        }
    }