<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Middleware;

use Lyrasoft\Firewall\Entity\Redirect;
use Lyrasoft\Firewall\Service\RedirectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Data\Collection;
use Windwalker\Uri\Uri;

use function Windwalker\collect;

class RedirectMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected RedirectService $redirectService,
        protected AppContext $app,
        protected bool $enabled = true,
        protected string|\BackedEnum|array|false|null $type = 'main',
        protected array|\Closure|null $list = null,
        protected bool $instantRedirect = false,
        protected array $ignores = [],
        protected ?\Closure $afterHit = null,
        protected int $cacheTtl = 3600,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $uri = clone $this->app->getSystemUri();
        $uri = $uri->withPath(rtrim($this->app->getSystemUri()->route, '/'));
        $route = trim($uri->toString(Uri::URI), '/');

        if ($this->ignores) {
            foreach ($this->ignores as $ignore) {
                if (fnmatch($ignore, $route)) {
                    return $handler->handle($request);
                }
            }
        }

        $redirects = collect();

        if ($this->type !== false) {
            $redirects = $redirects->merge($this->getAvailableRedirects());
        }

        if ($this->list !== null) {
            $redirects = $redirects->merge($this->loadFromList());
        }

        [$deferRedirects, $currentRedirects] = $redirects->partition(
            fn(Redirect $redirect) => $redirect->isNotFoundOnly()
        );

        $result = $this->redirectService->matchListAndProcessRegex($route, $currentRedirects);

        if ($result) {
            [$dest, $redirect] = $result;

            $this->redirectService->updateHits($redirect);

            $this->runAfterHit($dest, $redirect);

            return $this->app->redirect($dest, $redirect->getStatus(), $this->instantRedirect);
        }

        try {
            return $handler->handle($request);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 404) {
                $result = $this->redirectService->matchListAndProcessRegex($route, $deferRedirects);

                if ($result) {
                    [$dest, $redirect] = $result;

                    $this->redirectService->updateHits($redirect);

                    $this->runAfterHit($dest, $redirect);

                    return $this->app->redirect($dest, $redirect->getStatus(), $this->instantRedirect);
                }
            }

            throw $e;
        }
    }

    protected function loadFromList(): Collection
    {
        if ($this->list instanceof \Closure) {
            $items = collect($this->list);
        } else {
            $items = collect($this->list);
        }

        $defaultStatus = env('REDIRECT_DEFAULT_STATUS') ?: 301;

        foreach ($items as $key => $item) {
            if (is_string($item)) {
                $r = new Redirect();
                $r->setSrc($key);
                $r->setDest($item);
                $r->setStatus((int) $defaultStatus);
                $r->setStatus(1);
                $r->setParams(
                    [
                        'regex' => '1',
                        'not_found_only' => '0',
                        'handle_lang' => '0',
                    ]
                );
                $items[$key] = $r;
            } elseif (!$item instanceof Redirect) {
                throw new \RuntimeException('Wrong format of redirect rules.');
            }
        }

        return $items->values();
    }

    protected function runAfterHit(string $dest, Redirect $redirect): void
    {
        if (!$this->afterHit) {
            return;
        }

        $this->app->call(
            $this->afterHit,
            [
                'dest' => $dest,
                'redirect' => $redirect,
                Redirect::class => $redirect,
            ]
        );
    }

    /**
     * @return  Collection
     */
    protected function getAvailableRedirects(): Collection
    {
        return $this->redirectService->getAvailableRedirects($this->type, $this->cacheTtl);
    }
}
