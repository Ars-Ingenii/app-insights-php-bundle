<?php

declare(strict_types=1);

/*
 * This file is part of the App Insights PHP project.
 *
 * (c) Norbert Orzechowicz <norbert@orzechowicz.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppInsightsPHP\Symfony\AppInsightsPHPBundle\Listener;

use AppInsightsPHP\Client\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionListener implements EventSubscriberInterface
{
    private $telemetryClient;

    private $exceptionLogged;

    private $application;

    private $environment;

    private $exe;

    public function __construct(
        Client $telemetryClient,
        string $application,
        string $environment,
        string $exe
    ) {
        $this->telemetryClient = $telemetryClient;
        $this->application = $application;
        $this->environment = $environment;
        $this->exe = $exe;
        $this->exceptionLogged = false;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 1000],
        ];
    }

    public function onException(ExceptionEvent $event) : void
    {
        if (!$this->telemetryClient->getContext()->getInstrumentationKey()) {
            // instrumentation key is emtpy
            return;
        }

        if (!$this->telemetryClient->configuration()->exceptions()->isEnabled()) {
            return;
        }

        if ($this->exceptionLogged) {
            return;
        }

        $this->telemetryClient->trackException(
            $event->getThrowable(),
            [
                'application' => $this->application,
                'environment' => $this->environment,
                'exe' => $this->application,
                'requestId' => $event->getRequest()->attributes->get('X-Request-Id')
            ]
        );
        $this->exceptionLogged = true;
    }
}
