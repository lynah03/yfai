<?php
namespace App\Command;

use App\Entity\UserProfile;
use App\Service\PerfumeMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:match:user', description: 'Exécute le matcher pour un utilisateur donné')]
class MatchUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PerfumeMatcher $matcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Nom utilisateur (ex: lyna)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $name = (string)$input->getArgument('name');

        $user = $this->em->getRepository(UserProfile::class)->findOneBy(['name' => $name]);
        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" introuvable. Lance d’abord: php bin/console app:seed:user:lyna (ou crée son profil).', $name));
            return Command::FAILURE;
        }

        $ranked = $this->matcher->rankForUser($user, limit: 10);
        if (!$ranked) {
            $io->warning('Aucune recommandation.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Top recommandations pour "%s"', $name));
        foreach ($ranked as $i => $row) {
            $p = $row['perfume'];
            $io->writeln(sprintf(
                "%2d. %s — %s  | score: %.2f",
                $i+1,
                $p->getBrand()?->getName() ?? '—',
                $p->getName() ?? '—',
                $row['score']
            ));
            foreach ($row['reasons'] as $r) {
                $io->writeln("     • $r");
            }
        }
        return Command::SUCCESS;
    }
}
