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
use Windwalker\Data\Collection;
use Windwalker\DI\Attributes\Autowire;
use Windwalker\DI\Attributes\Service;

#[Service]
class FirewallService
{
    public function __construct(
        #[Autowire]
        protected IpRuleRepository $repository
    ) {
    }

    /**
     * @param  string            $ip
     * @param  iterable<IpRule>  $ipRules
     * @param  bool              $default
     *
     * @return  bool
     */
    public function isAllow(string $ip, iterable $ipRules, bool $default = false): bool
    {
        $address = IPFactory::parseAddressString($ip);

        if ($address === null) {
            return false;
        }

        /** @var IpRule $ipRule */
        foreach ($ipRules as $ipRule) {
            $ranges = static::createRangeInstances($ipRule->range);

            if (array_any($ranges, static fn($range) => $range->contains($address))) {
                return $ipRule->kind->isAllow();
            }
        }

        return $default;
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
}
