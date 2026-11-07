<?php

namespace App\Security;

use App\Http\ProblemResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $detail = 'api_auth_login' === $request->attributes->get('_route')
            ? 'Nieprawidłowa nazwa użytkownika lub hasło.'
            : 'Nieprawidłowy lub wygasły token.';

        return new ProblemResponse(401, $detail, [], ['WWW-Authenticate' => 'Bearer']);
    }
}
