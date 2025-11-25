<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Entity;

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

// phpcs:disable
// todo: remove this when phpcs supports 8.4
#[Table('redirects', 'redirect')]
#[\AllowDynamicProperties]
#[NewOrdering(NewOrdering::LAST)]
class Redirect implements EntityInterface
{
    use EntityTrait;

    #[Column('id'), PK, AutoIncrement]
    protected ?int $id = null;

    #[Column('type')]
    protected string $type = '';

    #[Column('src')]
    protected string $src = '';

    #[Column('dest')]
    protected string $dest = '';

    #[Column('status')]
    protected int $status = 0;

    #[Column('state')]
    #[Cast('int')]
    #[Cast(BasicState::class)]
    protected BasicState $state {
        set(BasicState|int $value) => $this->state = BasicState::wrap($value);
    }

    #[Column('ordering')]
    protected int $ordering = 0;

    #[Column('note')]
    protected string $note = '';

    #[Column('hits')]
    protected int $hits = 0;

    #[Column('last_hit')]
    #[CastNullable(ServerTimeCast::class)]
    protected ?Chronos $lastHit = null {
        set(\DateTimeInterface|string|null $value) => $this->lastHit = Chronos::tryWrap($value);
    }

    #[Column('created')]
    #[CastNullable(ServerTimeCast::class)]
    #[CreatedTime]
    protected ?Chronos $created = null {
        set(\DateTimeInterface|string|null $value) => $this->created = Chronos::tryWrap($value);
    }

    #[Column('modified')]
    #[CastNullable(ServerTimeCast::class)]
    #[CurrentTime]
    protected ?Chronos $modified = null {
        set(\DateTimeInterface|string|null $value) => $this->modified = Chronos::tryWrap($value);
    }

    #[Column('created_by')]
    #[Author]
    protected int $createdBy = 0;

    #[Column('modified_by')]
    #[Modifier]
    protected int $modifiedBy = 0;

    #[Column('params')]
    #[Cast(JsonCast::class)]
    protected array $params = [];

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

    public function isRegexEnabled(): bool
    {
        return (bool) ($this->params['regex'] ?? false);
    }

    public function isNotFoundOnly(): bool
    {
        return (bool) ($this->params['not_found_only'] ?? false);
    }

    public function isHandleLang(): bool
    {
        return (bool) ($this->params['handle_lang'] ?? false);
    }
}
