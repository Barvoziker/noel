<?php

namespace App\EventSubscriber;

use App\Service\Viewer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Dès que le propriétaire passe par l'admin, son navigateur est marqué « propriétaire » :
 * les pages publiques ne lui montreront plus jamais les réservations.
 */
class OwnerCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }
        if (!$this->security->isGranted('ROLE_ADMIN') || $request->cookies->get(Viewer::OWNER_COOKIE)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->setCookie(Viewer::roleCookie(Viewer::OWNER_COOKIE, true));
        $headers->setCookie(Viewer::roleCookie(Viewer::GIVER_COOKIE, false));
    }
}
