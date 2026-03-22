<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Service;

use IPLib\Address\AddressInterface;
use IPLib\Factory as IPFactory;
use IPLib\Range\RangeInterface;
use Lyrasoft\Firewall\Entity\IpRule;
use Lyrasoft\Firewall\Enum\IpRuleKind;
use Lyrasoft\Firewall\FirewallPackage;
use Lyrasoft\Firewall\Repository\IpRuleRepository;
use Windwalker\Cache\CachePool;
use Windwalker\Core\Application\AppContext;
use Windwalker\Data\Collection;
use Windwalker\DI\Attributes\Autowire;
use Windwalker\DI\Attributes\Service;
use Windwalker\ORM\ORM;
use Windwalker\Uri\UriNormalizer;

use Windwalker\Utilities\Arr;

use function Windwalker\chronos;

#[Service]
class FirewallService
{
    public function __construct(
        protected AppContext $app,
        protected ORM $orm,
        #[Autowire]
        protected IpRuleRepository $repository
    ) {
    }

    /**
     * @param  iterable<IpRule>  $ipRules
     * @param  string            $ip
     * @param  string            $routeName
     * @param  string            $routeUri
     *
     * @return IpRule|null
     */
    public function matchRule(iterable $ipRules, string $ip, string $routeName, string $routeUri): ?IpRule
    {
        $address = IPFactory::parseAddressString($ip);

        if ($address === null) {
            return null;
        }

        $routeUri = UriNormalizer::normalizePath($routeUri);
        $routeUri = UriNormalizer::ensureRoot($routeUri);

        /** @var IpRule $ipRule */
        foreach ($ipRules as $ipRule) {
            if ($ipRule->expiredAt && $ipRule->expiredAt->isPast()) {
                continue;
            }

            $paths = $ipRule->getPathList();

            if ($paths !== null && !$this->matchPaths($paths, $routeName, $routeUri)) {
                continue;
            }

            $ranges = static::createRangeInstances($ipRule->range);

            if (array_any($ranges, static fn($range) => $range->contains($address))) {
                return $ipRule;
            }
        }

        return null;
    }

    protected function matchPaths(array $excludes, string $routeName, string $routeUri): bool
    {
        foreach ($excludes as $exclude) {
            if (str_starts_with($exclude, '/')) {
                if ($routeUri === $exclude) {
                    return true;
                }

                if (str_contains($exclude, '*') && fnmatch($exclude, $routeUri)) {
                    return true;
                }
            } else {
                // Route
                if ($routeName === $exclude) {
                    return true;
                }

                if (str_contains($exclude, '*') && fnmatch($exclude, $routeName)) {
                    return true;
                }
            }
        }

        return in_array($routeName, $excludes, true);
    }

    public function matchAddress(AddressInterface $address, array $ipList = []): bool
    {
        foreach ($this->convertListToRangeArray($ipList) as $ipRange) {
            if ($ipRange === null) {
                continue;
            }

            if ($ipRange->contains($address)) {
                return true;
            }
        }

        return false;
    }

    public function blockIP(string|\UnitEnum $type, string $ip, \DateTimeInterface|string $expires = '1hour'): IpRule
    {
        $exists = $this->orm->findOne(
            IpRule::class,
            [
                'type' => $type,
                'kind' => IpRuleKind::BLOCK,
                'range' => $ip,
            ]
        );

        if ($exists) {
            return $exists;
        }

        $item = new IpRule();
        $item->type = $type;
        $item->kind = IpRuleKind::BLOCK;
        $item->range = $ip;
        $item->state = 1;
        $item->ordering = 0;
        $item->expiredAt = chronos($expires);

        $this->orm->createOne($item);

        return $item;
    }

    /**
     * @param  array|null  $list
     *
     * @return  array<RangeInterface|null>
     */
    protected function convertListToRangeArray(?array $list): array
    {
        if ($list === null) {
            return [];
        }

        return array_map(
            static::createRangeInstance(...),
            $list
        );
    }

    /**
     * @param  string|array  $ranges
     *
     * @return  array<RangeInterface>
     */
    public static function createRangeInstances(string|array $ranges): array
    {
        if (is_string($ranges)) {
            $ranges = explode(',', $ranges);
            $ranges = array_map('trim', $ranges);
        }

        return array_filter(
            array_map(
                static::createRangeInstance(...),
                $ranges
            )
        );
    }

    public static function createRangeInstance(string $range): ?RangeInterface
    {
        if (str_contains($range, '-')) {
            $parts = explode('-', $range, 2);

            return IPFactory::getRangesFromBoundaries($parts[0], $parts[1]);
        }

        return IPFactory::parseRangeString($range);
    }

    /**
     * @param  string|\BackedEnum|array|null  $type
     *
     * @return  Collection<IpRule>
     */
    public function getIpRules(string|\BackedEnum|array|null $type, int $ttl = 3600): Collection
    {
        if (WINDWALKER_DEBUG || $ttl === 0) {
            $this->getCachePool()->delete('ip-rule.' . json_encode($type));
        }

        return $this->getCachePool()
            ->call(
                'ip-rule.' . json_encode($type),
                fn () => $this->repository->getFrontListSelector($type)
                    ->ordering('ip_rule.ordering', 'ASC')
                    ->all(IpRule::class),
                $ttl
            );
    }

    public function getCachePool(): CachePool
    {
        return FirewallPackage::getCachePool();
    }

    public function clearExpired(): void
    {
        $this->orm->delete(IpRule::class)
            ->where('expired_at', '<', chronos())
            ->execute();
    }
}
