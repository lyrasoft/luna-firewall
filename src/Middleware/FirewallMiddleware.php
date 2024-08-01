<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Middleware;

use Lyrasoft\Firewall\Entity\IpRule;
use Lyrasoft\Firewall\Repository\IpRuleRepository;
use Lyrasoft\Firewall\Service\FirewallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Application\Context\AppRequestInterface;
use Windwalker\DI\Attributes\Autowire;

use function Windwalker\response;

class FirewallMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected AppContext $app,
        protected FirewallService $firewallService,
        protected bool $enabled = true,
        protected string|\BackedEnum|array|false|null $type = 'main',
        protected array $allowList = [],
        protected array $blockList = [],
        protected array $ignores = [],
        protected ?\Closure $afterHit = null,
        protected int $cacheTtl = 3600,
    ) {
        //
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $appRequest = $this->app->getAppRequest();

        if ($this->ignores) {
            $route = $appRequest->getSystemUri()->route;

            foreach ($this->ignores as $ignore) {
                if (fnmatch($ignore, $route)) {
                    return $handler->handle($request);
                }
            }
        }

        $currentIP = $appRequest->getClientIP();

        if ($this->type !== false) {
            [$allows, $blocks] = $this->firewallService->getAllowAndBlockList($this->type, $this->cacheTtl);

            $this->allowList = array_merge($this->allowList, $allows);
            $this->blockList = array_merge($this->blockList, $blocks);
        }

        if (!$this->firewallService->isAllow($currentIP, $this->allowList, $this->blockList)) {
            $this->runAfterHit();

            return response('', 403);
        }

        return $handler->handle($request);
    }

    protected function runAfterHit(): void
    {
        if (!$this->afterHit) {
            return;
        }

        $this->app->call($this->afterHit);
    }

    public function getAllowList(): array
    {
        return $this->allowList;
    }

    public function setAllowList(array $allowList): static
    {
        $this->allowList = $allowList;

        return $this;
    }

    public function getBlockList(): array
    {
        return $this->blockList;
    }

    public function setBlockList(array $blockList): static
    {
        $this->blockList = $blockList;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
