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
    * [Select DB Type](#select-db-type)
    * [Custom List](#custom-list)
    * [Disable](#disable-1)
    * [Hook](#hook-1)
  * [Cache](#cache)
    * [Cache Lifetime](#cache-lifetime)
    * [Cache Clear](#cache-clear)
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
            ignores: [
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

If you enabne the `Regex`, you may use variables start with `$` to insert matched string. For example, a `foo/*/edit/(\d+)`, can redirect to `new/path/$1/edit/$2`

### Other Params

- `Only 404`: Only redirect if a page is 404, if page URL exists, won't redirect.
- `Handle Locale`: If this site is multi-language, this params will auto auto detect the starting ;anguage prefix and auto add it to dest path, you may use `{lang}` in dest path to custom set lang alias position.

### Use Different Type from DB

Redirect tables has `type` colimn, you can use `admin/redirect/list/{type}` to manage different types.

And if you want to choose types for middleware, you can do this:

```php
    // ...

    'middlewares' => [
        \Windwalker\DI\create(
            RedirectMiddleware::class,
            type: 'other_type',
            ignores: [
                'admin/*'
            ]
        ),
        
        // ...
    ],
```

The type supports `string|Enum|array|null|false`, if you send `NULL` into it, means all redirect records. If you send `FALSE`, means don't use DB recods.

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
            ignores: [
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
            ignores: [
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
    )

    // ...
```

### Admin IP Rules Management

Select Allow or Block, and enter the IP Range format:

![](https://github.com/user-attachments/assets/b22e6598-fe16-4070-8182-9a471398afda)

The supported formats:

Type | Syntax | Details
--- | --- | ---
IPV6|`::1`|Short notation
IPV4|`192.168.0.1`|
Range|`192.168.0.0-192.168.1.60`|Includes all IPs from *192.168.0.0* to *192.168.0.255*<br />and from *192.168.1.0* to *198.168.1.60*
Wild card|`192.168.0.*`|IPs starting with *192.168.0*<br />Same as IP Range `192.168.0.0-192.168.0.255`
Subnet mask|`192.168.0.0/255.255.255.0`|IPs starting with *192.168.0*<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`
CIDR Mask|`192.168.0.0/24`|IPs starting with *192.168.0*<br />Same as `192.168.0.0-192.168.0.255` and `192.168.0.*`<br />and `192.168.0.0/255.255.255.0`

We use [mlocati/ip-lib](https://github.com/mlocati/ip-lib) as IP Range parser.


### Select DB Type

You can also access different type from `ip-rule/list/{type}`.

And set type name to middleware

```php
    ->middleware(
        FirewallMiddleware::class,
        type: 'foo'
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
            afterHit: raw(function (AppRequest $appRequest) {
                \Windwalker\Core\Manager\Logger::info('Attack from: ' . $appRequest->getClientIp());
            })
        ),
```

## Cache

### Cache Lifetime

Both middlewares has a `cacheTtl` param, default is `3600` seconds.

```php
        \Windwalker\DI\create(
            FirewallMiddleware::class,
            cacheTtl: 3600
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

