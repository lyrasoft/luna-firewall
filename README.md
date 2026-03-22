# LYRASOFT Firewall Package

<!-- TOC -->
* [LYRASOFT Firewall Package](#lyrasoft-firewall-package)
  * [Installation](#installation)
    * [Language Files](#language-files)
  * [Register Admin Menu](#register-admin-menu)
  * [Redirect](#redirect)
    * [The Source Path Rules](#the-source-path-rules)
    * [The Dest Path](#the-dest-path)
    * [Other Params](#other-params)
    * [Use Different Type from DB](#use-different-type-from-db)
    * [Use Custom List](#use-custom-list)
    * [Instant Redirect](#instant-redirect)
    * [Disable](#disable)
    * [Hook](#hook)
  * [IP Allow/Block (Firewall)](#ip-allowblock-firewall)
    * [Admin IP Rules Management](#admin-ip-rules-management)
    * [Paths and Domains](#paths-and-domains)
    * [Select DB Type](#select-db-type)
    * [Custom List](#custom-list)
    * [Disable](#disable-1)
    * [Hook](#hook-1)
    * [FirewallMiddleware params](#firewallmiddleware-params)
  * [Cache](#cache)
    * [Cache Lifetime](#cache-lifetime)
    * [Cache Clear](#cache-clear)
    * [Cache Disable](#cache-disable)
<!-- TOC -->

## Installation

Install from composer

```shell
composer require lyrasoft/firewall
```

Then copy files to project

```shell
php windwalker pkg:install lyrasoft/firewall -t routes -t migrations
```

### Language Files

Add this line to admin & front middleware if you don't want to override languages:

```php
$this->lang->loadAllFromVendor('lyrasoft/firewall', 'ini');

// OR
$this->lang->loadAllFromVendor(\Lyrasoft\Firewall\FirewallPackage::class, 'ini');

```

Or run this command to copy languages files:

```shell
php windwalker pkg:install lyrasoft/firewall -t lang
```

## Register Admin Menu

Edit `resources/menu/admin/sidemenu.menu.php`

```php
$menu->link($this->trans('unicorn.title.grid', title: $this->trans('firewall.redirect.title')))
    ->to($nav->to('redirect_list')->var('type', 'main'))
    ->icon('fal fa-angles-right');

$menu->link($this->trans('unicorn.title.grid', title: $this->trans('firewall.ip.rule.title')))
    ->to($nav->to('ip_rule_list')->var('type', 'main'))
    ->icon('fal fa-network-wired');

```

## Redirect

Add `RedirectMiddleware` to `etc/app/main.php`

```php
use Lyrasoft\Firewall\Middleware\RedirectMiddleware;

    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            excludes: [
                'admin/*'
            ]
        ),
        
        // ...
    ],
```

Now you can add redirect records at admin:

![](https://github.com/user-attachments/assets/3919fa71-b182-4e9e-a20c-c363f4dd3963)

### The Source Path Rules

- Add `/` at start, the path will compare from site base root (not domain root).
- If you enable the `Regex`:
    - Add `*` will compare a path segment with any string.
    - Add `**` will compare cross-segments.
    - You can add custom regex rules, like: `/foo/(\d+)`

### The Dest Path

Thr dest path can be relative path: `foo/bar` or full URL: `https://simular.co/foo/bar`.

If you enabne the `Regex`, you may use variables start with `$` to insert matched string. For example, a
`foo/*/edit/(\d+)`, can redirect to `new/path/$1/edit/$2`

### Other Params

- `Only 404`: Only redirect if a page is 404, if page URL exists, won't redirect.
- `Handle Locale`: If this site is multi-language, this params will auto auto detect the starting ;anguage prefix and
  auto add it to dest path, you may use `{lang}` in dest path to custom set lang alias position.

### Use Different Type from DB

Redirect tables has `type` colimn, you can use `admin/redirect/list/{type}` to manage different types.

And if you want to choose types for middleware, you can do this:

```php
    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            type: 'other_type',
            excludes: [
                'admin/*'
            ]
        ),
        
        // ...
    ],
```

The type supports `string|Enum|array|null|false`, if you send `NULL` into it, means all redirect records. If you send
`FALSE`, means don't use DB recods.

### Use Custom List

You can use custom redirect list, custom list will auto-enable the regex:

This settings will merge DB list and custom list.

```php
    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            type: 'flower',
            list: [
                'foo/bar' => 'hello/world',            
                'foo/yoo/*' => 'hello/mountain/$1',            
            ],
            excludes: [
                'admin/*'
            ]
        ),
        
        // ...
    ],
```

This settings will disable DB list and only use custom list.

```php
    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            type: false,
            list: [
                'foo/bar' => 'hello/world',            
                'foo/yoo/*' => 'hello/mountain/$1',            
            ],
            excludes: [
                'admin/*'
            ]
        ),
        
        // ...
    ],
```

Custom List can use Closure to generate list:

```php
// ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            // ...
            list: raw(function (FooService $fooService) {
                return ...; 
            });
        ),
        
        // ...
    ],
```

The custom list redirect status code default is `301`, if you want to use other status, set it to  
`REDIRECT_DEFAULT_STATUS` env varialbe.

### Instant Redirect

If there has some reason you can not wait `RedirectResponse` return, you may use instant redirect:

```php
    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            // ...
            instantRedirect: true,
        ),
        
        // ...
    ],
```

### Disable

If you wanr to disable this middleware in debug mode, add this options:

```php
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            enabled: !WINDWALKER_DEBUG
        ),
```

### Hook

Add `afterHit` hook that you can do somthing or log if redirect hit.

```php
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            afterHit: raw(function (string $dest, \Redirect $redirect) {
                \Windwalker\Core\Manager\Logger::info('Redirect to: ' . $dest);
            })
        ),
```

-----

## IP Allow/Block (Firewall)

To enable IP Rules, add `FirewallMiddleware` to `front.route.php`

```php
use Lyrasoft\Firewall\Middleware\FirewallMiddleware;

    // ...

    ->middleware(
        FirewallMiddleware::class,
        // ...
        defaultAction: \Lyrasoft\Firewall\Enum\IpRuleKind::ALLOW, // Or BLOCK, default is ALLOW
        logger: 'firewall/blocks' // Or LoggerInterface instance, default is NULL
    )

    // ...
```

### Admin IP Rules Management

Select Allow or Block, and enter the IP Range format:

![](https://github.com/user-attachments/assets/b22e6598-fe16-4070-8182-9a471398afda)

The supported formats:

| Type        | Syntax                      | Details                                                                                                                       |
|-------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| IPV6        | `::1`                       | Short notation                                                                                                                |
| IPV4        | `192.168.0.1`               |                                                                                                                               |
| Range       | `192.168.0.0-192.168.1.60`  | Includes all IPs from *192.168.0.0* to *192.168.0.255*<br />and from *192.168.1.0* to *198.168.1.60*                          |
| Wild card   | `192.168.0.*`               | IPs starting with *192.168.0*<br />Same as IP Range `192.168.0.0-192.168.0.255`                                               |
| Subnet mask | `192.168.0.0/255.255.255.0` | IPs starting with *192.168.0*<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`                                      |
| CIDR Mask   | `192.168.0.0/24`            | IPs starting with *192.168.0*<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`<br />and `192.168.0.0/255.255.255.0` |

And you can use `,` to separate multiple IPs or IP Ranges. For example, `0.0.0.0/0,::/0` means all IPs includes both
IPV4 and IPV6.

We use [mlocati/ip-lib](https://github.com/mlocati/ip-lib) as IP Range parser.

### Paths and Domains

After `0.2.0` there has a paths textarea, you can set domains / URLs or paths that this rule will effect, 1 line for
1 path.

![Image](https://github.com/user-attachments/assets/b682b032-8847-4e04-9933-f60867414282)

For example, if you only want to block some IPs to access admin panel, you can set paths to `/admin/*`,
or `/admin/login` if you only want to block access to login page.

You can also set domains or full URLs, for example, you can block some IPs to
access `https://*.foo.com/bar`, or `https://foo.com/bar/*/baz`.

### Select DB Type

You can also access different type from `ip-rule/list/{type}`.

And set type name to middleware

```php
    ->middleware(
        FirewallMiddleware::class,
        type: 'foo',
    )
```

type can also supports string, array and enum. Use `NULL` to select all, `FALSE` to disable DB.

### Custom List

If you want to manually set ip list, `FirewallMiddleware` custom list must use 2 lists, `allowList` and `blockList`.

```php
    ->middleware(
        FirewallMiddleware::class,
        type: false,
        allowList: [
            '0.0.0.0',
            '144.122.*.*',
        ],
        blockList: [
            '165.2.90.45',
            '222.44.55.66',
        ],
        allowAsFirst: true, // Or false, default is false
    )
```

### Disable

If you wanr to disable this middleware in debug mode, add this options:

```php
        \Windwalker\DI\create(
            FirewallMiddleware::class,
            enabled: !WINDWALKER_DEBUG
        ),
```

### Hook

Add `afterHit` hook that you can do somthing or log if an IP was be blocked.

```php
        \Windwalker\DI\create(
            FirewallMiddleware::class,
            afterHit: fn () => function (AppRequest $appRequest) {
                \Windwalker\Core\Manager\Logger::info('Attack from: ' . $appRequest->getClientIp());
            }
        ),
```

### FirewallMiddleware params

| Param                | Type                | Default                  | Description                                                                                                         |
|----------------------|---------------------|--------------------------|---------------------------------------------------------------------------------------------------------------------|
| `enabled`            | `bool`              | `true`                   | Enable this middleware                                                                                              |
| `type`               | `mixed`             | `main`                   | Entity type, use `NULL` to select all types, `FALSE` to disable DB                                                  | 
| `allowList`          | `array`             | `[]`                     | Custom allow list, will merge to DB items.                                                                          |
| `blockList`          | `array`             | `[]`                     | Custom block list, will merge to DB items.                                                                          |
| `allowAsFirst`       | `bool`              | `false`                  | When use custom list, if an IP is in both allow and block list, this option will decide which rule is first.        |
| `excludes`           | `?Closure or array` | `null`                   | Exclude some paths from firewall, support closure or array, if path match any exclude rule, firewall will not work. |
| `defaultAction`      | `IpRuleKind`        | `IpRuleKind::ALLOW`      | If no IP matched, this option will decide to allow or block this IP.                                                |
| `logger`             | `LoggerInterface`   | `string` or `NullLogger` | Logger instance or logger name, default is `NullLogger`.                                                            |
| `afterHit`           | `?Closure`          | `null`                   | A hook that will be called after an IP was blocked, you can do something or log in this hook.                       |
| `cacheTtl`           | `int`               | `3600`                   | Cache lifetime in seconds, default is 3600 seconds.                                                                 |
| `clearExpiredChance` | `number`            | `1/100`                  | Every request has this chance to clear expired cache, default is `1/100`, set `0` to disable.                       |

## Cache

### Cache Lifetime

Both middlewares has a `cacheTtl` param, default is `3600` seconds.

```php
        \Windwalker\DI\create(
            FirewallMiddleware::class,
            cacheTtl: 3600,
            clearExpiredChance: 1 / 100 // Default is 1/100, means every 100 request will clear expired cache once, set it to 0 to disable auto clear expired cache
        ),
```

### Cache Clear

Everytime you edit `Redirect` or `IpRule` will auto clear all caches.

The cache files is located at `caches/firewall/`, and you can add `firewall` to clear cache command in `composer.json`

```json
        "post-autoload-dump": [
...
"php windwalker cache:clear renderer html firewall" <-- Add firewall
],
```

### Cache Disable

Cache will disable in debug mode or when `ttl` set to `0`.
