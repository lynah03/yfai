<?php
    namespace App\Command;
    
    use App\Entity\Employee;
    use App\Entity\UserProfile;
    use App\Service\PerfumeMatcher;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\Console\Attribute\AsCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Style\SymfonyStyle;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    use Symfony\Component\Uid\Uuid;
    
    #[AsCommand(name: 'app:create:user', description: 'Exécute le matcher pour un utilisateur donné')]
    class CreateAdminUserCommand extends Command
    {
        public function __construct(
            private EntityManagerInterface $em,
            private PerfumeMatcher $matcher,
            private UserPasswordHasherInterface $passwordHasher
        ) {
            parent::__construct();
        }
        
        protected function configure(): void
        {
        
        }
        
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $io   = new SymfonyStyle($input, $output);
            $employee = new Employee();
            $employee->setEmail('admin@yfai.com');
            $employee->setRoles(['ROLE_ADMIN']);
            $employee->setPassword($this->passwordHasher->hashPassword($employee, 'adminpassword'));
            $employee->setFirstName('Admin');
            $employee->setLastName('User');
            $employee->setIsActive(true);
            $employee->setLastLoginAt(new \DateTimeImmutable());
            $employee->setUuid(uuid::v4());
            $this->em->persist($employee);
            $this->em->flush();
            $io->success('Admin user created with email:'.$employee->getEmail().' and password: adminpassword');
            return Command::SUCCESS;
        }
    }
