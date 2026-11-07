<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $html = file_get_contents($this->projectDir.'/templates/home.html');

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
