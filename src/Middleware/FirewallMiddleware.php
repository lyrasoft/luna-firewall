<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Middleware;

use Lyrasoft\Firewall\Entity\IpRule;
use Lyrasoft\Firewall\Enum\IpRuleKind;
use Lyrasoft\Firewall\Service\FirewallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Manager\Logger;
use Windwalker\Core\Middleware\RoutingExcludesTrait;
use Windwalker\DI\Attributes\NoAutowire;

use function Windwalker\collect;
use function Windwalker\response;

class FirewallMiddleware implements MiddlewareInterface
{
    use RoutingExcludesTrait;

    public function __construct(
        protected AppContext $app,
        protected FirewallService $firewallService,
        protected bool $enabled = true,
        protected string|\BackedEnum|array|false|null $type = 'main',
        protected array $allowList = [],
        protected array $blockList = [],
        protected bool $allowAsFirst = false,
        protected \Closure|array|null $excludes = null,
        protected IpRuleKind $defaultAction = IpRuleKind::ALLOW,
        #[NoAutowire]
        protected LoggerInterface|string $logger = new NullLogger(),
        protected ?\Closure $afterHit = null,
        protected int $cacheTtl = 3600,
        protected float|int $clearExpiredChance = 1 / 100
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

        if ($this->isExclude()) {
            $this->clearExpired();

            return $handler->handle($request);
        }

        $currentIP = $appRequest->getClientIP();

        $ipRules = collect();

        if ($this->type !== false) {
            $ipRules = $this->firewallService->getIpRules($this->type, $this->cacheTtl);
        }

        if ($this->allowAsFirst) {
            $ipRules->push(...$this->ipListToRuleEntities($this->allowList, IpRuleKind::ALLOW));
            $ipRules->push(...$this->ipListToRuleEntities($this->blockList, IpRuleKind::BLOCK));
        } else {
            $ipRules->push(...$this->ipListToRuleEntities($this->allowList, IpRuleKind::ALLOW));
            $ipRules->push(...$this->ipListToRuleEntities($this->blockList, IpRuleKind::BLOCK));
        }

        $matchedRule = $this->firewallService->matchRule(
            $ipRules,
            $currentIP,
            $this->app->getMatchedRoute()?->getName() ?? '',
            $this->app->getSystemUri()->route
        );

        $isAllow = $matchedRule?->kind->isAllow() ?? $this->defaultAction->isAllow();

        if (!$isAllow) {
            $this->runAfterHit($currentIP);

            return response('', 403);
        }

        $this->clearExpired();

        return $handler->handle($request);
    }

    protected function ipListToRuleEntities(array $ips, IpRuleKind $kind): array
    {
        $rules = [];

        foreach ($ips as $ip) {
            $rule = new IpRule();
            $rule->range = $ip;
            $rule->kind = $kind;
        }

        return $rules;
    }

    protected function runAfterHit(string $currentIP): void
    {
        if (!$this->afterHit) {
            return;
        }

        $this->getLogger()->info(
            sprintf(
                'IP blocked: %s',
                $currentIP
            )
        );

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

    public function getExcludes(): mixed
    {
        return $this->excludes;
    }

    protected function getLogger(): LoggerInterface
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }

        return Logger::getChannel($this->logger);
    }

    protected function clearExpired(): void
    {
        if (!$this->shouldClear()) {
            return;
        }

        $this->firewallService->clearExpired();
    }

    protected function shouldClear(): bool
    {
        if ($this->clearExpiredChance >= 1) {
            return true;
        }

        return random_int(0, 999_999) / 1_000_000 < $this->clearExpiredChance;
    }
}
