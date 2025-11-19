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

#[AsCommand(name: 'app:test-matcher')]
class TestMatcherCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PerfumeMatcher $matcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('userId', InputArgument::OPTIONAL, 'User profile ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('userId');

        if ($userId) {
            $user = $this->em->getRepository(UserProfile::class)->find($userId);
            if (!$user) {
                $output->writeln('<error>User not found</error>');
                return Command::FAILURE;
            }

            $results = $this->matcher->rankForUser($user, 10);
            $output->writeln("Results for user #{$userId}:");

        } else {
            $quiz = [
                'preferred_notes'   => ['Vanilla'],
                'preferred_accords' => ['Gourmand'],
                'concentration'     => 'EDP',
                'budget_max'        => 80,
            ];
            $results = $this->matcher->recommendForNonUser($quiz, 10);
            $output->writeln("Results for non-user quiz:");
        }

        foreach ($results as $r) {
            $p = $r['perfume'];
            $output->writeln(sprintf(
                '- %s (%s): %.2f',
                $p->getName(),
                $p->getBrand()?->getName(),
                $r['score']
            ));
            $output->writeln('    '.implode(' | ', $r['reasons']));
        }

        return Command::SUCCESS;
    }
}
