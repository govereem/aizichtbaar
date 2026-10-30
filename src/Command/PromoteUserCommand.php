<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote', description: 'Geeft een gebruiker de beheerdersrol')]
class PromoteUserCommand
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'E-mailadres van de gebruiker')] string $email): int
    {
        $user = $this->users->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error(sprintf('Geen gebruiker gevonden met e-mailadres %s.', $email));

            return Command::FAILURE;
        }

        $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])));
        $this->em->flush();

        $io->success(sprintf('%s is nu beheerder.', $email));

        return Command::SUCCESS;
    }
}
