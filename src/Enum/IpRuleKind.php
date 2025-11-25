<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall\Enum;

use Windwalker\Utilities\Attributes\Enum\Color;
use Windwalker\Utilities\Contract\LanguageInterface;
use Windwalker\Utilities\Enum\EnumRichInterface;
use Windwalker\Utilities\Enum\EnumRichTrait;

enum IpRuleKind: string implements EnumRichInterface
{
    use EnumRichTrait;

    #[Color('danger')]
    case BLOCK_LIST = 'block';

    #[Color('success')]
    case ALLOW_LIST = 'allow';

    public function trans(LanguageInterface $lang, ...$args): string
    {
        return $lang->trans('firewall.ip.rule.kind.' . $this->getKey());
    }
}
