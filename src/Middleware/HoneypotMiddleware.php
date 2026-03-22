<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Middleware;

use Lyrasoft\Firewall\Service\FirewallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Http\BrowserNext;
use Windwalker\Core\Middleware\RoutingExcludesTrait;

class HoneypotMiddleware implements MiddlewareInterface
{
    use RoutingExcludesTrait;

    public function __construct(
        protected AppContext $app,
        protected FirewallService $firewallService,
        protected BrowserNext $browserNext,
        protected string|\UnitEnum $type = 'bot',
        protected ?array $matchParams = null,
        protected ?\Closure $matchCallback = null,
        protected ?\Closure $blockHandler = null,
        protected bool $allowGoodRobot = true,
        protected \Closure|array|null $excludes = null,
        protected \DateTimeInterface|string $expires = '1hour',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isExclude() || $this->isGoodRobot()) {
            return $handler->handle($request);
        }

        $matched = $this->isParamsMatched() || $this->isCallbackMatched();

        $res = $handler->handle($request);

        if ($matched) {
            $ip = $this->app->getAppRequest()->getClientIP();

            if ($this->blockHandler) {
                $this->app->call($this->blockHandler, ['ip' => $ip]);
            } else {
                $this->firewallService->blockIP(
                    $this->type,
                    $ip,
                    $this->expires
                );
            }
        }

        return $res;
    }

    protected function isParamsMatched(): bool
    {
        if ($this->matchParams === null) {
            return false;
        }

        foreach ($this->matchParams as $key => $values) {
            $v = $this->app->input($key);

            if (in_array($v, (array) $values, true)) {
                return true;
            }
        }

        return false;
    }

    protected function isCallbackMatched(): bool
    {
        if ($this->matchCallback === null) {
            return false;
        }

        return $this->app->call($this->matchCallback) === true;
    }

    public function getExcludes(): mixed
    {
        return $this->excludes;
    }

    protected function isGoodRobot(): bool
    {
        return $this->allowGoodRobot && $this->browserNext->isRobot();
    }
}
