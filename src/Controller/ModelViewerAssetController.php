<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ModelViewerAssetController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    #[Route(
        '/modelviewer-assets/{environment}/{path}',
        name: 'app_modelviewer_asset',
        requirements: ['environment' => 'live|classic', 'path' => '.+'],
        methods: ['GET', 'HEAD']
    )]
    public function asset(string $environment, string $path, Request $request): Response
    {
        if (
            preg_match('#(^|/)\.\.?(/|$)#', $path)
            || !preg_match('#^[A-Za-z0-9_./%~-]+$#', $path)
        ) {
            return new Response('Invalid model asset path.', Response::HTTP_BAD_REQUEST);
        }

        $forwardHeaders = ['Accept-Encoding' => 'identity'];
        foreach (['Range', 'If-None-Match', 'If-Modified-Since'] as $header) {
            if ($request->headers->has($header)) {
                $forwardHeaders[$header] = $request->headers->get($header);
            }
        }

        try {
            $upstream = $this->httpClient->request(
                $request->isMethod('HEAD') ? 'HEAD' : 'GET',
                sprintf('https://wow.zamimg.com/modelviewer/%s/%s', $environment, $path),
                [
                    'headers' => $forwardHeaders,
                    'timeout' => 30,
                ]
            );
            $statusCode = $upstream->getStatusCode();
            $upstreamHeaders = $upstream->getHeaders(false);
        } catch (\Throwable) {
            return new Response('The model asset provider is unavailable.', Response::HTTP_BAD_GATEWAY);
        }

        $response = $request->isMethod('HEAD')
            ? new Response('', $statusCode)
            : new StreamedResponse(function () use ($upstream): void {
                foreach ($this->httpClient->stream($upstream) as $chunk) {
                    if (!$chunk->isTimeout()) {
                        echo $chunk->getContent();
                    }
                }
            }, $statusCode);

        foreach (['content-type', 'content-range', 'accept-ranges', 'etag', 'last-modified'] as $header) {
            if (isset($upstreamHeaders[$header][0])) {
                $response->headers->set($header, $upstreamHeaders[$header][0]);
            }
        }
        $response->setPublic();
        $response->setMaxAge(2592000);
        $response->setSharedMaxAge(2592000);

        return $response;
    }
}
