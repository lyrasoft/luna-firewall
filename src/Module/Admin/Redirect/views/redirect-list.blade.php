<?php

declare(strict_types=1);

namespace App\View;

/**
 * Global variables
 * --------------------------------------------------------------
 * @var  $app       AppContext      Application context.
 * @var  $vm        \Lyrasoft\Firewall\Module\Admin\Redirect\RedirectListView The view model object.
 * @var  $uri       SystemUri       System Uri information.
 * @var  $chronos   ChronosService  The chronos datetime service.
 * @var  $nav       Navigator       Navigator object to build route.
 * @var  $asset     AssetService    The Asset manage service.
 * @var  $lang      LangService     The language translation service.
 */

use Lyrasoft\Firewall\Module\Admin\Redirect\RedirectListView;
use Lyrasoft\Firewall\Entity\Redirect;
use Unicorn\Workflow\BasicStateWorkflow;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Asset\AssetService;
use Windwalker\Core\DateTime\ChronosService;
use Windwalker\Core\Language\LangService;
use Windwalker\Core\Router\Navigator;
use Windwalker\Core\Router\SystemUri;

/**
 * @var $item Redirect
 */

$workflow = $app->service(BasicStateWorkflow::class);
?>

@extends('admin.global.body-list')

@section('toolbar-buttons')
    @include('list-toolbar')
@stop

@section('content')
    <form id="admin-form" action="" x-data="{ grid: $store.grid }"
        x-ref="gridForm"
        data-ordering="{{ $ordering }}"
        method="post">

        <x-filter-bar :form="$form" :open="$showFilters"></x-filter-bar>

        {{-- RESPONSIVE TABLE DESC --}}
        <div class="d-block d-lg-none mb-3">
            @lang('unicorn.grid.responsive.table.desc')
        </div>

        <div class="grid-table table-responsive-lg">
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    {{-- Toggle --}}
                    <th style="width: 1%">
                        <x-toggle-all></x-toggle-all>
                    </th>

                    {{-- State --}}
                    <th style="width: 5%" class="text-nowrap">
                        <x-sort field="redirect.state">
                            @lang('unicorn.field.state')
                        </x-sort>
                    </th>

                    {{-- SRC --}}
                    <th class="text-nowrap">
                        <x-sort field="redirect.src">
                            @lang('firewall.redirect.field.src')
                        </x-sort>
                    </th>

                    {{-- Dest --}}
                    {{--<th class="text-nowrap">--}}
                    {{--    <x-sort field="redirect.dest">--}}
                    {{--        目標網址--}}
                    {{--    </x-sort>--}}
                    {{--</th>--}}

                    {{-- Status --}}
                    <th class="text-nowrap">
                        <x-sort field="redirect.status">
                            @lang('firewall.redirect.field.status')
                        </x-sort>
                    </th>

                    {{-- ORDERING --}}
                    <th style="width: 10%" class="text-nowrap">
                        <x-order-sort
                            :enabled="$vm->reorderEnabled($ordering)"
                            asc="redirect.ordering ASC"
                            desc="redirect.ordering DESC">
                        </x-order-sort>
                    </th>

                    {{-- Delete --}}
                    <th style="width: 1%" class="text-nowrap">
                        @lang('unicorn.field.delete')
                    </th>

                    {{-- ID --}}
                    <th style="width: 1%" class="text-nowrap text-end">
                        <x-sort field="redirect.id">
                            @lang('unicorn.field.id')
                        </x-sort>
                    </th>
                </tr>
                </thead>

                <tbody>
                @forelse($items as $i => $item)
                    <tr>
                        {{-- Checkbox --}}
                        <td>
                            <x-row-checkbox :row="$i" :id="$item->getId()"></x-row-checkbox>
                        </td>

                        {{-- State --}}
                        <td>
                            <x-state-dropdown color-on="text"
                                button-style="width: 100%"
                                use-states
                                :workflow="$workflow"
                                :id="$item->getId()"
                                :value="$item->state"
                            ></x-state-dropdown>
                        </td>

                        {{-- SRC --}}
                        <td>
                            <div>
                                <strong>From:</strong>
                                <a href="{{ $nav->to('redirect_edit')->id($item->getId()) }}">
                                    {{ $item->getSrc() }}
                                </a>
                            </div>
                            <div class="text-muted small mt-1">
                                <strong>To:</strong>
                                <span>{{ $item->getDest() }}</span>
                            </div>

                            <div class=" mt-1">
                                @if ($item->isRegexEnabled())
                                    <span class="badge bg-warning">
                                        @lang('firewall.redirect.list.badge.regex')
                                    </span>
                                @endif
                                @if ($item->isNotFoundOnly())
                                    <span class="badge bg-primary">
                                        @lang('firewall.redirect.list.badge.404.only')
                                    </span>
                                @endif
                                @if ($item->isHandleLang())
                                    <span class="badge bg-dark">
                                        @lang('firewall.redirect.list.badge.handle.lang')
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- Dest --}}
                        {{--<td>--}}
                        {{--    {{ $item->getDest() }}--}}
                        {{--</td>--}}

                        {{-- Status --}}
                        <td>
                            {{ $item->getStatus() }}
                        </td>

                        {{-- Ordering --}}
                        <td class="text-end">
                            <x-order-control
                                :enabled="$vm->reorderEnabled($ordering)"
                                :id="$item->getId()"
                                :value="$item->getOrdering()"
                            ></x-order-control>
                        </td>

                        {{-- Delete --}}
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                @click="grid.deleteItem('{{ $item->getId() }}')"
                                data-dos
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>

                        {{-- ID --}}
                        <td class="text-end">
                            {{ $item->getId() }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="30">
                            <div class="c-grid-no-items text-center" style="padding: 125px 0;">
                                <h3 class="text-secondary">@lang('unicorn.grid.no.items')</h3>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div>
                <x-pagination :pagination="$pagination"></x-pagination>
            </div>
        </div>

        <div class="d-none">
            <input name="_method" type="hidden" value="PUT" />
            <x-csrf></x-csrf>
        </div>

        <x-batch-modal :form="$form" namespace="batch"></x-batch-modal>
    </form>

@stop
