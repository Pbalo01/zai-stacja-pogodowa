<?php

namespace App\Security;

use App\Http\ProblemResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

// API odpowiada 401 zamiast przekierowania na formularz logowania.
final class ProblemEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new ProblemResponse(401, 'Wymagane uwierzytelnienie tokenem Bearer.', [], ['WWW-Authenticate' => 'Bearer']);
    }
}
