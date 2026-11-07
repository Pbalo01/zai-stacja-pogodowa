<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findOneBy(['tokenHash' => ApiTokenManager::hash($accessToken)]);
        if (null === $token || $token->isExpired()) {
            throw new BadCredentialsException('Nieprawidłowy lub wygasły token.');
        }

        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
