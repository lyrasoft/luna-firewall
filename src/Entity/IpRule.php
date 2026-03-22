<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Entity;

use Lyrasoft\Firewall\Enum\IpRuleKind;
use Lyrasoft\Firewall\FirewallPackage;
use Lyrasoft\Luna\Attributes\Author;
use Lyrasoft\Luna\Attributes\Modifier;
use Unicorn\Attributes\NewOrdering;
use Unicorn\Enum\BasicState;
use Windwalker\Core\DateTime\Chronos;
use Windwalker\Core\DateTime\ServerTimeCast;
use Windwalker\ORM\Attributes\AutoIncrement;
use Windwalker\ORM\Attributes\Cast;
use Windwalker\ORM\Attributes\CastNullable;
use Windwalker\ORM\Attributes\Column;
use Windwalker\ORM\Attributes\CreatedTime;
use Windwalker\ORM\Attributes\CurrentTime;
use Windwalker\ORM\Attributes\EntitySetup;
use Windwalker\ORM\Attributes\PK;
use Windwalker\ORM\Attributes\Table;
use Windwalker\ORM\Cast\JsonCast;
use Windwalker\ORM\EntityInterface;
use Windwalker\ORM\EntityTrait;
use Windwalker\ORM\Event\AfterSaveEvent;
use Windwalker\ORM\Metadata\EntityMetadata;
use Windwalker\Utilities\Arr;

use function Windwalker\unwrap_enum;

// phpcs:disable
// todo: remove this when phpcs supports 8.4
#[Table('ip_rules', 'ip_rule')]
#[\AllowDynamicProperties]
#[NewOrdering(NewOrdering::LAST)]
class IpRule implements EntityInterface
{
    use EntityTrait;

    #[Column('id'), PK, AutoIncrement]
    public ?int $id = null;

    #[Column('type')]
    public string $type = '' {
        set(string|\UnitEnum $value) => $this->type = unwrap_enum($value);
    }

    #[Column('kind')]
    #[Cast(IpRuleKind::class)]
    public IpRuleKind $kind {
        set(IpRuleKind|string $value) => $this->kind = IpRuleKind::wrap($value);
    }

    #[Column('range')]
    public string $range = '';

    #[Column('paths')]
    public string $path = '';

    #[Column('state')]
    #[Cast('int')]
    #[Cast(BasicState::class)]
    public BasicState $state {
        set(BasicState|int $value) => $this->state = BasicState::wrap($value);
    }

    #[Column('ordering')]
    public int $ordering = 0;

    #[Column('note')]
    public string $note = '';

    #[Column('expired_at')]
    #[CastNullable(ServerTimeCast::class)]
    public ?Chronos $expiredAt = null {
        set(\DateTimeInterface|string|null $value) => $this->expiredAt = Chronos::tryWrap($value);
    }

    #[Column('created')]
    #[CastNullable(ServerTimeCast::class)]
    #[CreatedTime]
    public ?Chronos $created = null {
        set(\DateTimeInterface|string|null $value) => $this->created = Chronos::tryWrap($value);
    }

    #[Column('modified')]
    #[CastNullable(ServerTimeCast::class)]
    #[CurrentTime]
    public ?Chronos $modified = null {
        set(\DateTimeInterface|string|null $value) => $this->modified = Chronos::tryWrap($value);
    }

    #[Column('created_by')]
    #[Author]
    public int $createdBy = 0;

    #[Column('modified_by')]
    #[Modifier]
    public int $modifiedBy = 0;

    #[Column('params')]
    #[Cast(JsonCast::class)]
    public array $params = [];

    #[EntitySetup]
    public static function setup(EntityMetadata $metadata): void
    {
        //
    }

    #[AfterSaveEvent]
    public static function afterSave(AfterSaveEvent $event)
    {
        FirewallPackage::getCachePool()->clear();
    }

    public function getPathList(): ?array
    {
        if ($this->path === '') {
            return null;
        }

        return Arr::explodeAndClear("\n", $this->path);
    }
}
