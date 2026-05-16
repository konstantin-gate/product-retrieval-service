<?php

declare(strict_types=1);

namespace App\Presentation\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restores the locale from the session on each request.
 * Must run after the SessionListener so that the session is available.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Must run after SessionListener (priority 128) but before LocaleListener (priority 16)
            KernelEvents::REQUEST => [['onKernelRequest', 17]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $locale = $session->get('_locale');

        if (null !== $locale && '' !== $locale) {
            $request->setLocale($locale);
        }
    }
}
