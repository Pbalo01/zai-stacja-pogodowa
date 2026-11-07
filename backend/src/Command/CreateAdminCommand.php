<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Bez argumentów dane biorą się z ADMIN_USERNAME / ADMIN_PASSWORD (start kontenera).
// Istniejącego konta nie zmienia, chyba że podano --update-password.
#[AsCommand(name: 'app:create-admin', description: 'Tworzy konto administratora')]
final class CreateAdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(default::ADMIN_USERNAME)%')] private readonly ?string $envUsername = null,
        #[Autowire('%env(default::ADMIN_PASSWORD)%')] private readonly ?string $envPassword = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Login administratora (domyślnie ADMIN_USERNAME)')] ?string $username = null,
        #[Argument('Hasło (domyślnie ADMIN_PASSWORD)')] ?string $password = null,
        #[Option('Zmień hasło, jeśli konto już istnieje')] bool $updatePassword = false,
    ): int {
        $username = $username ?: $this->envUsername ?: $io->ask('Login administratora', 'admin');
        $password = $password ?: $this->envPassword ?: $io->askHidden('Hasło (min. 8 znaków)');

        if (!$username || !$password) {
            $io->error('Podaj login i hasło (argumenty lub ADMIN_USERNAME / ADMIN_PASSWORD).');

            return Command::FAILURE;
        }
        if (mb_strlen($password) < 8) {
            $io->error('Hasło musi mieć co najmniej 8 znaków.');

            return Command::FAILURE;
        }

        $user = $this->users->findOneBy(['username' => $username]);
        if (null !== $user && !$updatePassword) {
            $io->note(sprintf('Konto „%s” już istnieje – bez zmian.', $username));

            return Command::SUCCESS;
        }

        $user ??= new User($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Zapisano konto administratora „%s”.', $username));

        return Command::SUCCESS;
    }
}
