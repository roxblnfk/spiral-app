<?php

declare(strict_types=1);

namespace Modules\HttpDumps\Interfaces\Http\Handler;

use App\Application\Commands\HandleReceivedEvent;
use App\Application\Event\EventType;
use App\Application\Service\HttpHandler\HandlerInterface;
use Carbon\Carbon;
use Modules\HttpDumps\Application\EventHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Spiral\Cqrs\CommandBusInterface;
use Spiral\Http\ResponseWrapper;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageInterface;

final readonly class AnyHttpRequestDump implements HandlerInterface
{
    private BucketInterface $bucket;

    public function __construct(
        private CommandBusInterface $commands,
        private EventHandlerInterface $handler,
        private ResponseWrapper $responseWrapper,
        StorageInterface $storage,
    ) {
        $this->bucket = $storage->bucket('attachments');
    }

    public function priority(): int
    {
        return 0;
    }

    public function handle(ServerRequestInterface $request, \Closure $next): ResponseInterface
    {
        $eventType = $this->listenEvent($request);

        if ($eventType === null) {
            return $next($request);
        }

        $payload = $this->createPayload($request);

        $event = $this->handler->handle($payload);

        $this->commands->dispatch(
            new HandleReceivedEvent(type: $eventType->type, payload: $event, project: $eventType->project),
        );

        return $this->responseWrapper->create(200);
    }

    private function createPayload(ServerRequestInterface $request): array
    {
        $uri = \ltrim($request->getUri()->getPath(), '/');
        $id = \md5(Carbon::now()->toDateTimeString());

        return [
            'received_at' => Carbon::now()->toDateTimeString(),
            'host' => $request->getHeaderLine('Host'),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => $uri,
                'headers' => $request->getHeaders(),
                'body' => (string) $request->getBody(),
                'query' => $request->getQueryParams(),
                'post' => $request->getParsedBody() ?? [],
                'cookies' => $request->getCookieParams(),
                'files' => \array_map(
                    function (UploadedFileInterface $attachment) use ($id) {
                        $this->bucket->write(
                            $filename = $id . '/' . $attachment->getClientFilename(),
                            $attachment->getStream(),
                        );

                        return [
                            'id' => \md5($filename),
                            'name' => $attachment->getClientFilename(),
                            'uri' => $filename,
                            'size' => $attachment->getSize(),
                            'mime' => $attachment->getClientMediaType(),
                        ];
                    },
                    $request->getUploadedFiles(),
                ),
            ],
        ];
    }

    private function listenEvent(ServerRequestInterface $request): ?EventType
    {
        /** @var EventType|null $event */
        $event = $request->getAttribute('event');

        if ($event?->type === 'http-dump') {
            return $event;
        }

        return null;
    }
}
