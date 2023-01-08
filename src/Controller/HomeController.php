<?php

namespace App\Controller;

use App\DependencyInjection\Attribute\Cache;
use App\Process\Home\RepoClass;
use App\Process\Home\RepoInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;

class HomeController extends AbstractController
{
    private RepoInterface $repo;
    private RepoInterface $repoRegular;

    #[Required]
    public function setDependencies(
        #[Autowire(service: RepoClass::class)] #[Cache] RepoInterface $repo,
        #[Autowire(service: RepoClass::class)] RepoInterface $repoRegular
    ) {
        $this->repo = $repo;
        $this->repoRegular = $repoRegular;
    }

    #[Route('/')]
    public function __invoke(): Response
    {
        return new Response($this->repo->byStringParam('a').$this->repo->byStringParam('a').$this->repo->byStringParam('b').$this->repoRegular->byStringParam('b'));
    }
}
