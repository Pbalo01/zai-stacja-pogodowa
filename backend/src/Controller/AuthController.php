<?php

namespace App\Controller;

use App\Dto\PasswordChangeInput;
use App\Entity\User;
use App\Security\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    // Logowanie obsługuje json_login z firewalla (LoginSuccessHandler);
    // kontroler wykona się tylko, gdy żądanie nie zawiera danych JSON.
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new BadRequestHttpException('Wymagane pola username i password w formacie JSON.');
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function logout(Request $request, ApiTokenManager $tokens): Response
    {
        $token = ApiTokenManager::extractBearer($request);
        if (null !== $token) {
            $tokens->revoke($token);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/password', name: 'api_auth_password', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload] PasswordChangeInput $input,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        if (!$hasher->isPasswordValid($user, $input->currentPassword)) {
            throw new AccessDeniedHttpException('Bieżące hasło jest niepoprawne.');
        }

        $user->setPassword($hasher->hashPassword($user, $input->newPassword));
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
