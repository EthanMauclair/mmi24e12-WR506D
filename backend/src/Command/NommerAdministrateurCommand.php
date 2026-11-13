<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Donne un rôle d'administration à un membre existant. Sert à nommer le premier super admin :
 * ensuite, il gère les rôles depuis le back office.
 */
#[AsCommand(name: 'app:admin:nommer', description: 'Nomme un membre super admin (propriétaire du site), administrateur, ou le remet simple membre')]
final class NommerAdministrateurCommand extends Command
{
    private const ROLES = ['super-admin' => ['ROLE_SUPER_ADMIN'], 'admin' => ['ROLE_ADMIN'], 'membre' => []];

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail du membre')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'super-admin, admin ou membre', 'super-admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = (string) $input->getOption('role');
        if (!isset(self::ROLES[$role])) {
            $io->error('Rôle inconnu : choisissez super-admin, admin ou membre.');

            return Command::INVALID;
        }

        $utilisateur = $this->utilisateurs->findOneBy(['email' => mb_strtolower((string) $input->getArgument('email'))]);
        if (null === $utilisateur) {
            $io->error('Aucun compte ne correspond à cet e-mail.');

            return Command::FAILURE;
        }

        $utilisateur->setRoles(self::ROLES[$role]);
        $this->em->flush();
        $io->success(\sprintf('%s est maintenant %s.', $utilisateur->getPseudo(), $role));

        return Command::SUCCESS;
    }
}
