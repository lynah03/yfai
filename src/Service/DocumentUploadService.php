<?php
	
	namespace App\Service;
	
	use Symfony\Component\String\Slugger\SluggerInterface;
    
    class DocumentUploadService
	{
		public function __construct(
			private SluggerInterface $slugger,
			private \Doctrine\ORM\EntityManagerInterface $entityManager,
		){
		}
		
		public function uploadDocument($image, $directory)
		{
			$originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
			$safeFilename = $this->slugger->slug($originalFilename);
			$newFilename = $safeFilename.'-'.uniqid('', true).'.'.$image->guessExtension();
			$image->move(
				$directory,
				$newFilename
			);
			
			return $newFilename;
		}
		
	}
