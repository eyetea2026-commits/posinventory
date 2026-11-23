<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public function __construct() {}

    public function sections(): array
    {
        return [
            [
                'label' => null,
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
                ],
            ],
            [
                'label' => 'User Management',
                'items' => [
                    ['label' => 'User Management', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'users'],
                ],
            ],
            [
                'label' => 'Product Management',
                'items' => [
                    ['label' => 'Category', 'route' => 'admin.categories.index', 'pattern' => 'admin.categories.*', 'icon' => 'folder'],
                    ['label' => 'Product Management', 'route' => 'admin.products.index', 'pattern' => 'admin.products.*', 'icon' => 'package'],
                    ['label' => 'Inventory', 'route' => 'admin.inventory.index', 'pattern' => 'admin.inventory.*', 'icon' => 'archive'],
                    ['label' => 'Discounts', 'route' => 'admin.discounts.index', 'pattern' => 'admin.discounts.*', 'icon' => 'percent'],
                ],
            ],
            [
                'label' => 'Supply Chain',
                'items' => [
                    ['label' => 'Suppliers', 'route' => 'admin.suppliers.index', 'pattern' => 'admin.suppliers.*', 'icon' => 'truck'],
                    ['label' => 'Purchase Orders', 'route' => 'admin.purchase-orders.index', 'pattern' => 'admin.purchase-orders.*', 'icon' => 'shopping-cart'],
                    ['label' => 'Stock Receiving', 'route' => 'admin.stock-receivings.index', 'pattern' => 'admin.stock-receivings.*', 'icon' => 'clipboard-check'],
                    ['label' => 'Stock Adjustments', 'route' => 'admin.stock-adjustments.index', 'pattern' => 'admin.stock-adjustments.*', 'icon' => 'sliders-horizontal'],
                ],
            ],
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Return & Damage', 'route' => 'admin.sales-returns.index', 'pattern' => ['admin.sales-returns.*', 'admin.damages.*'], 'icon' => 'rotate-ccw'],
                    ['label' => 'Reports', 'route' => 'admin.reports.index', 'pattern' => 'admin.reports.*', 'icon' => 'bar-chart-3'],
                ],
            ],
        ];
    }

    public function render(): View|Closure|string
    {
        return view('components.sidebar');
    }
}
