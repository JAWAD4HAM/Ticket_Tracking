<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\LocaleSwitcher;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security, private LocaleSwitcher $localeSwitcher)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $locale = $user->getLocale();
            $event->getRequest()->setLocale($locale);
            $this->localeSwitcher->setLocale($locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -20],
        ];
    }
}
