<?php
namespace App\Command;

use App\Entity\UserProfile;
use App\Entity\UserNotePreference;
use App\Entity\UserBrandPreference;
use App\Entity\UserConcentrationPreference;
use App\Entity\Brand;
use App\Entity\Note;
use App\Enum\Concentration;
use App\Enum\Gender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:user:lyna', description: 'Crée/maj l’utilisateur lyna avec des préférences par défaut')]
class SeedUserLynaCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->em;

        // find-or-create user
        $user = $em->getRepository(UserProfile::class)->findOneBy(['name' => 'lyna'])
             ?? (new UserProfile())->setName('lyna')->setGender(Gender::UNDISCLOSED);
        $em->persist($user);

        // helpers
        $brand = fn(string $n) => $em->getRepository(Brand::class)->findOneBy(['name'=>$n]);
        $note  = fn(string $n) => $em->getRepository(Note::class)->findOneBy(['name'=>$n]);

        $upsertNotePref = function(UserProfile $u, Note $n, int $w) use ($em) {
            $repo = $em->getRepository(UserNotePreference::class);
            $p = $repo->findOneBy(['user'=>$u,'note'=>$n]) ?? (new UserNotePreference())->setUser($u)->setNote($n);
            $p->setWeight($w); $em->persist($p);
        };
        $upsertBrandPref = function(UserProfile $u, Brand $b, int $w) use ($em) {
            $repo = $em->getRepository(UserBrandPreference::class);
            $p = $repo->findOneBy(['user'=>$u,'brand'=>$b]) ?? (new UserBrandPreference())->setUser($u)->setBrand($b);
            $p->setWeight($w); $em->persist($p);
        };
        $upsertConcPref = function(UserProfile $u, Concentration $c, int $w) use ($em) {
            $repo = $em->getRepository(UserConcentrationPreference::class);
            $p = $repo->findOneBy(['user'=>$u,'concentration'=>$c]) ?? (new UserConcentrationPreference())->setUser($u)->setConcentration($c);
            $p->setWeight($w); $em->persist($p);
        };

        // prefs compatibles avec la seed full-demo
        if ($n = $note('Rose'))     { $upsertNotePref($user, $n, +5); }
        if ($n = $note('Bergamot')) { $upsertNotePref($user, $n, +3); }
        if ($n = $note('Oud'))      { $upsertNotePref($user, $n, -3); }

        if ($b = $brand('Maison Cipro')) { $upsertBrandPref($user, $b, +4); }
        if ($b = $brand('Roja Dove'))    { $upsertBrandPref($user, $b, +2); }

        $upsertConcPref($user, Concentration::PARFUM, 5);
        $upsertConcPref($user, Concentration::EDP,    4);

        $em->flush();
        $io->success('Utilisateur "lyna" prêt avec préférences par défaut.');
        return Command::SUCCESS;
    }
}
