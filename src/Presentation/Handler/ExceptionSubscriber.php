<?php

declare(strict_types=1);

namespace App\Presentation\Handler;

use App\Domain\Exception\CacheException;
use App\Domain\Exception\CounterException;
use App\Domain\Exception\InvalidProductIdException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Exception\QueueException;
use App\Domain\Exception\SourceUnavailableException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception', priority: 100)]
final readonly class ExceptionSubscriber
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        $isJson = 'json' === $request->getRequestFormat()
            || \str_contains($request->headers->get('Accept') ?? '', 'application/json');

        if (!$isJson) {
            return;
        }

        $statusCode = match (true) {
            $throwable instanceof InvalidProductIdException => 400,
            $throwable instanceof ProductNotFoundException => 404,
            $throwable instanceof SourceUnavailableException,
            $throwable instanceof CacheException,
            $throwable instanceof CounterException,
            $throwable instanceof QueueException => 503,
            default => 500,
        };

        $message = match (true) {
            $throwable instanceof InvalidProductIdException => 'Invalid product ID',
            $throwable instanceof ProductNotFoundException => 'Product not found',
            $throwable instanceof SourceUnavailableException,
            $throwable instanceof CacheException,
            $throwable instanceof CounterException,
            $throwable instanceof QueueException => 'Service unavailable',
            default => 'Internal server error',
        };

        $event->setResponse(new JsonResponse(['error' => $message], $statusCode));
    }
}
