<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Service\DashboardManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardController extends AbstractController
{
    private const SAMPLE_PRODUCT_COUNT = 5;

    private const SEED_MIN_COUNT = 1;
    private const SEED_MAX_COUNT = 1000;
    private const SEED_DEFAULT_COUNT = 1000;

    public function __construct(
        private DashboardManager $manager,
        private TranslatorInterface $translator,
        #[Autowire(service: 'limiter.dashboard_toggle')]
        private RateLimiterFactory $dashboardToggleLimiter,
        #[Autowire(service: 'limiter.dashboard_seed')]
        private RateLimiterFactory $dashboardSeedLimiter,
    ) {
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $healthStatus = $this->manager->getHealthStatus();
        $redisHealthy = $healthStatus['redis'];

        $fallbackApplied = $this->manager->ensureConfigurationValidity($redisHealthy);

        if ($fallbackApplied) {
            $this->addFlash('warning', $this->translator->trans('flash.redis_fallback_notice'));

            return $this->redirectToRoute('dashboard');
        }

        return $this->render('dashboard/index.html.twig', [
            'healthStatus' => $healthStatus,
            'currentConfig' => $this->manager->getCurrentConfig(),
            'productIds' => $this->manager->getSampleProductIds(self::SAMPLE_PRODUCT_COUNT),
            'allToggleOptions' => $this->manager->getAllToggleOptions(),
            'availableToggles' => $this->manager->getAvailableToggles($redisHealthy),
        ]);
    }

    #[Route('/toggle', name: 'dashboard_toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        $limiter = $this->dashboardToggleLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse(['error' => $this->translator->trans('error.rate_limit')], 429);
        }

        if (!$this->isCsrfTokenValid('toggle', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => $this->translator->trans('error.invalid_csrf')], 403);
        }

        $key = (string) $request->request->get('key');
        $value = (string) $request->request->get('value');

        $allowedToggles = $this->manager->getAvailableToggles();

        if (!\array_key_exists($key, $allowedToggles)) {
            return new JsonResponse(['error' => $this->translator->trans('error.unknown_toggle_key')], 400);
        }

        if (!\in_array($value, $allowedToggles[$key], true)) {
            return new JsonResponse(['error' => $this->translator->trans('error.invalid_toggle_value')], 400);
        }

        $this->manager->setToggle($key, $value);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'status' => 'ok',
                'key' => $key,
                'value' => $value,
                'notice' => $this->translator->trans('flash.toggle_notice'),
            ]);
        }

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/seed', name: 'dashboard_seed', methods: ['POST'])]
    public function seed(Request $request): Response
    {
        $limiter = $this->dashboardSeedLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', $this->translator->trans('error.rate_limit'));

            return $this->redirectToRoute('dashboard');
        }

        if (!$this->isCsrfTokenValid('seed', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.invalid_csrf'));

            return $this->redirectToRoute('dashboard');
        }

        $count = (int) $request->request->get('count', (string) self::SEED_DEFAULT_COUNT);

        if ($count < self::SEED_MIN_COUNT || $count > self::SEED_MAX_COUNT) {
            $this->addFlash('error', $this->translator->trans('flash.count_range_error'));

            return $this->redirectToRoute('dashboard');
        }

        $this->manager->seed($count);
        $this->addFlash('success', $this->translator->trans('flash.seed_success', ['count' => $count]));

        return $this->redirectToRoute('dashboard');
    }
}
