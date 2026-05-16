<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Service\DashboardManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const ALLOWED_TOGGLES = [
        'ACTIVE_PRODUCT_SOURCE' => ['elasticsearch', 'mysql'],
        'ACTIVE_CACHE_DRIVER' => ['file', 'redis', 'null'],
        'ACTIVE_COUNTER_MODE' => ['async', 'filesystem', 'redis', 'null'],
    ];

    private const SEED_MIN_COUNT = 1;
    private const SEED_MAX_COUNT = 1000;
    private const SEED_DEFAULT_COUNT = 1000;

    public function __construct(
        private DashboardManager $manager,
    ) {
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'healthStatus' => $this->manager->getHealthStatus(),
            'currentConfig' => $this->manager->getCurrentConfig(),
            'productIds' => $this->manager->getSampleProductIds(5),
            'allowedToggles' => self::ALLOWED_TOGGLES,
        ]);
    }

    #[Route('/toggle', name: 'dashboard_toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $key = (string) $request->request->get('key');
        $value = (string) $request->request->get('value');

        if (!\array_key_exists($key, self::ALLOWED_TOGGLES)) {
            return new JsonResponse(['error' => 'Unknown toggle key'], 400);
        }

        if (!\in_array($value, self::ALLOWED_TOGGLES[$key], true)) {
            return new JsonResponse(['error' => 'Invalid toggle value'], 400);
        }

        $this->manager->setToggle($key, $value);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'status' => 'ok',
                'key' => $key,
                'value' => $value,
                'notice' => 'Run cache:clear and restart worker to apply changes.',
            ]);
        }

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/seed', name: 'dashboard_seed', methods: ['POST'])]
    public function seed(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('seed', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('dashboard');
        }

        $count = (int) $request->request->get('count', (string) self::SEED_DEFAULT_COUNT);

        if ($count < self::SEED_MIN_COUNT || $count > self::SEED_MAX_COUNT) {
            $this->addFlash('error', 'Count must be between 1 and 1000.');

            return $this->redirectToRoute('dashboard');
        }

        $this->manager->seed($count);
        $this->addFlash('success', "Successfully generated {$count} products.");

        return $this->redirectToRoute('dashboard');
    }
}
