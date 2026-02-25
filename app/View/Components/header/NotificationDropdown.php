<?php

namespace App\View\Components\header;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Dropdown thông báo: không query DB khi render layout.
 * Số thông báo + danh sách load sau bằng JS (fetch dropdown-data) để trang hiển thị nhanh.
 */
class NotificationDropdown extends Component
{
    public function render(): View|Closure|string
    {
        return view('components.header.notification-dropdown');
    }
}
