<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles locale switching via session.
 * Symfony's built-in LocaleListener automatically reads `_locale` from the session
 * when `framework.session: true` is configured.
 */
final class LocaleController extends AbstractController
{
    private const ALLOWED_LOCALES = ['cs', 'en'];

    #[Route('/locale/{locale}', name: 'locale_switch', methods: ['GET'])]
    public function switchLocale(string $locale, Request $request, SessionInterface $session): Response
    {
        if (!\in_array($locale, self::ALLOWED_LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        $session->set('_locale', $locale);
        $request->setLocale($locale);

        $referer = $request->headers->get('Referer');
        if (null !== $referer && '' !== $referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('dashboard');
    }
}
