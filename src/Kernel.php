<?php
    
    namespace App;
    
    //use App\DependencyInjection\CacheAdapterPass;
    use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\HttpKernel\Kernel as BaseKernel;
    
    class Kernel extends BaseKernel
    {
        use MicroKernelTrait;
        public function boot(): void
        {
            parent::boot();
            //date_default_timezone_set($this->getContainer()->getParameter('timezone'));
            
        }
        protected function build(ContainerBuilder $container): void
        {
            parent::build($container);
            
            //$container->addCompilerPass(new CacheAdapterPass());
        }
        
        
    }
