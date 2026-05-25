<?php

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\ORM\Tools\SchemaTool;

require dirname(__DIR__).'/vendor/autoload.php';

$projectDir = dirname(__DIR__);

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv($projectDir.'/.env');
}

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test'
    && str_starts_with((string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? ''), 'sqlite:///')
) {
    $databasePath = $projectDir.'/var/phpunit.db';

    if (is_file($databasePath)) {
        unlink($databasePath);
    }

    $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? App\Kernel::class;
    $kernel = new $kernelClass('test', true);
    $kernel->boot();

    /** @var Doctrine\Persistence\ManagerRegistry $doctrine */
    $doctrine = $kernel->getContainer()->get('doctrine');
    $entityManager = $doctrine->getManager();
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

    if ($metadata !== []) {
        (new SchemaTool($entityManager))->createSchema($metadata);
    }

    $entityManager->close();
    $kernel->shutdown();
}
