<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class DocumentUploadService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function uploadDocument(UploadedFile $file, string $directory): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename)->lower();

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid('', true), $extension);

        $file->move($directory, $newFilename);

        return $newFilename;
    }
}