<?php

namespace App\Controller;

use App\DependencyInjection\Attribute\Cache;
use App\Process\Home\RepoClass;
use App\Process\Home\RepoInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(#[Autowire(service: RepoClass::class)] #[Cache] private RepoInterface $repo, #[Autowire(service: RepoClass::class)] private RepoInterface $repoRegular)
    {
    }

    #[Route('/')]
    public function __invoke(): Response
    {
        return new Response($this->repo->byStringParam('a').$this->repo->byStringParam('a').$this->repo->byStringParam('b').$this->repoRegular->byStringParam('b'));
    }
}
