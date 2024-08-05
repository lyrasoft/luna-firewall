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

    public function isAllow(string $ip, ?array $allowList = null, ?array $blockList = null): bool
    {
        if (empty($blockList) && empty($allowList)) {
            return true;
        }

        $address = IPFactory::parseAddressString($ip);

        if ($address === null) {
            return false;
        }

        if (empty($blockList)) {
            return $this->matchAddress($address, $allowList);
        }

        if (empty($allowList)) {
            return !$this->matchAddress($address, $blockList);
        }

        return $this->matchAddress($address, $allowList) &&
            !$this->matchAddress($address, $blockList);
    }

    public function matchAddress(AddressInterface $address, array $list): bool
    {
        foreach ($this->convertListToRangeArray($list) as $ipRange) {
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

    public function getAllowAndBlockList(string|\BackedEnum|array|null $type, int $ttl = 3600): array
    {
        $rules = $this->getIpRules($type, $ttl);

        [$blocks, $allows] = $rules->partition(fn(IpRule $rule) => $rule->getKind() === IpRuleKind::BLOCK_LIST);

        $blocks = $blocks->column('range')->dump();
        $allows = $allows->column('range')->dump();

        return [$allows, $blocks];
    }

    public function getCachePool(): CachePool
    {
        return FirewallPackage::getCachePool();
    }
}
