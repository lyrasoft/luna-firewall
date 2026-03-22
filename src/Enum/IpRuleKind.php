<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Enum;

use Windwalker\Utilities\Attributes\Enum\Color;
use Windwalker\Utilities\Contract\LanguageInterface;
use Windwalker\Utilities\Enum\EnumRichInterface;
use Windwalker\Utilities\Enum\EnumRichTrait;
use Windwalker\Utilities\Enum\EnumTranslatableInterface;
use Windwalker\Utilities\Enum\EnumTranslatableTrait;

enum IpRuleKind: string implements EnumRichInterface
{
    use EnumRichTrait;

    #[Color('danger')]
    case BLOCK = 'block';

    #[Color('success')]
    case ALLOW = 'allow';

    public function translateKey(string $name): string
    {
        return 'firewall.ip.rule.kind.' . $name;
    }

    public function isAllow(): bool
    {
        return $this === self::ALLOW;
    }
}
