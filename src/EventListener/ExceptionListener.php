<?php

namespace App\EventListener;

use App\Exception\FailureResponseException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof FailureResponseException) {
            $error = $exception->getError();
            var_dump("Code: {$error->getCode()}, error: {$error->getError()}, message: {$error->getMessage()}");
            var_dump($error->getDetails());
        }
    }
}
