<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Middleware;

use Lyrasoft\Firewall\Entity\IpRule;
use Lyrasoft\Firewall\Enum\IpRuleKind;
use Lyrasoft\Firewall\Repository\IpRuleRepository;
use Lyrasoft\Firewall\Service\FirewallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Application\Context\AppRequestInterface;
use Windwalker\Core\Middleware\RoutingExcludesTrait;
use Windwalker\DI\Attributes\Autowire;

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
        protected \Closure|array $ignores = [],
        protected IpRuleKind $defaultAction = IpRuleKind::ALLOW,
        protected bool $allowFirst = false,
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

        if ($this->isExclude()) {
            $handler->handle($request);
        }

        $currentIP = $appRequest->getClientIP();

        $ipRules = collect();

        if ($this->type !== false) {
            $ipRules = $this->firewallService->getIpRules($this->type, $this->cacheTtl);
        }

        if ($this->allowFirst) {
            $ipRules->push(...$this->ipListToRuleEntities($this->allowList, IpRuleKind::ALLOW));
            $ipRules->push(...$this->ipListToRuleEntities($this->blockList, IpRuleKind::BLOCK));
        } else {
            $ipRules->push(...$this->ipListToRuleEntities($this->allowList, IpRuleKind::ALLOW));
            $ipRules->push(...$this->ipListToRuleEntities($this->blockList, IpRuleKind::BLOCK));
        }

        if (!$this->firewallService->isAllow($currentIP, $ipRules, $this->defaultAction->isAllow())) {
            $this->runAfterHit();

            return response('', 403);
        }

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

    public function getExcludes(): mixed
    {
        return $this->ignores;
    }
}
