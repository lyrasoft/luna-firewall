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

    protected function installModules(
        PackageInstaller $installer,
        string $name,
        array $modules = ['front', 'admin', 'model']
    ): void {
        $pascal = StrNormalize::toPascalCase($name);

        if (in_array('admin', $modules, true)) {
            $installer->installModules(
                [
                    static::path("src/Module/Admin/$pascal/**/*") => "@source/Module/Admin/$pascal",
                ],
                ['Lyrasoft\\Firewall\\Module\\Admin' => 'App\\Module\\Admin'],
                ['modules', $name . '_admin'],
            );
        }

        if (in_array('front', $modules, true)) {
            $installer->installModules(
                [
                    static::path("src/Module/Front/$pascal/**/*") => "@source/Module/Front/$pascal",
                ],
                ['Lyrasoft\\Firewall\\Module\\Front' => 'App\\Module\\Front'],
                ['modules', $name . '_front']
            );
        }

        if (in_array('model', $modules, true)) {
            $installer->installModules(
                [
                    static::path("src/Entity/$pascal.php") => '@source/Entity',
                    static::path("src/Repository/{$pascal}Repository.php") => '@source/Repository',
                ],
                [
                    'Lyrasoft\\Firewall\\Entity' => 'App\\Entity',
                    'Lyrasoft\\Firewall\\Repository' => 'App\\Repository',
                ],
                ['modules', $name . '_model']
            );
        }
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
