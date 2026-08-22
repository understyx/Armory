<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiDocumentationController extends AbstractController
{
    #[Route('/api', name: 'api_documentation', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('api/index.html.twig');
    }
}
