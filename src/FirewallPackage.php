<?php

declare(strict_types=1);

namespace Lyrasoft\Firewall;

use Lyrasoft\Firewall\Entity\IpRule;
use Lyrasoft\Firewall\Entity\Redirect;
use Windwalker\Cache\CachePool;
use Windwalker\Cache\Serializer\PhpSerializer;
use Windwalker\Cache\Storage\FileStorage;
use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Core\Package\PackageInstaller;
use Windwalker\Utilities\StrNormalize;

class FirewallPackage extends AbstractPackage
{
    /**
     * @throws \ReflectionException
     */
    public function install(PackageInstaller $installer): void
    {
        $installer->installConfig(static::path('etc/*.php'), 'config');
        $installer->installLanguages(static::path('resources/languages/**/*.ini'), 'lang');
        $installer->installMigrations(static::path('resources/migrations/**/*'), 'migrations');
        $installer->installRoutes(static::path('routes/**/*.php'), 'routes');

        $installer->installMVCModules(Redirect::class, ['Admin'], true);
        $installer->installMVCModules(IpRule::class, ['Admin'], true);
    }

    public static function getCachePool()
    {
        return new CachePool(
            new FileStorage(
                WINDWALKER_CACHE . '/firewall'
            ),
            new PhpSerializer(),
        );
    }
}
