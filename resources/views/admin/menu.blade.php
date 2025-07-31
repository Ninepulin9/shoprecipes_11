<style> 
    .bg-menu-theme .menu-header:before {
        width: 0rem !important;
    }
</style>

<?php
use App\Models\Config;
use App\Helpers\RoleHelper;

$config = Config::first();
$userRole = RoleHelper::getCurrentRole();
$user = RoleHelper::getCurrentUser();
?>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder">{{ $config->name }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">

        {{-- Dashboard - เฉพาะ Owner --}}
        @if($userRole === 'owner')
            <li class="menu-item {{ ($function_key == 'dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bxs-dashboard"></i>
                    <div data-i18n="Analytics">Dashboard</div>
                </a>
            </li>
        @endif

        {{-- ออร์เดอร์ --}}
        @if(RoleHelper::canReceiveOrders())
            <li class="menu-item {{ ($function_key == 'Memberorder') ? 'active' : '' }}">
                <a href="{{ route('Memberorder') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-store"></i>
                    <div data-i18n="Analytics">ออเดอร์หน้าร้าน</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'MemberorderRider') ? 'active' : '' }}">
                <a href="{{ route('MemberorderRider') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-cycling"></i>
                    <div data-i18n="Analytics">ออเดอร์ออนไลน์</div>
                </a>
            </li>

            @if(in_array($userRole, ['owner', 'manager', 'cashier']))
                <li class="menu-item {{ ($function_key == 'order') ? 'active' : '' }}">
                    <a href="{{ route('adminorder') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-receipt"></i>
                        <div data-i18n="Analytics">จัดการออร์เดอร์</div>
                    </a>
                </li>
            @endif
        @endif

        {{-- จัดการข้อมูล --}}
        @if(RoleHelper::canManageSystem())
            <li class="menu-header small text-uppercase"><span class="menu-header-text">จัดการข้อมูล</span></li>

            <li class="menu-item {{ ($function_key == 'category') ? 'active' : '' }}">
                <a href="{{ route('category') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-collection"></i>
                    <div data-i18n="Basic">หมวดหมู่อาหาร</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'menu') ? 'active' : '' }}">
                <a href="{{ route('menu') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-food-menu"></i>
                    <div data-i18n="Basic">เมนูอาหาร</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'promotion') ? 'active' : '' }}">
                <a href="{{ route('promotion') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bxs-megaphone"></i>
                    <div data-i18n="Analytics">โปรโมชั่น</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'table') ? 'active' : '' }}">
                <a href="{{ route('table') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-barcode"></i>
                    <div data-i18n="Analytics">จัดการโต้ะ</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'rider') ? 'active' : '' }}">
                <a href="{{ route('rider') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-cycling"></i>
                    <div data-i18n="Analytics">ไรเดอร์</div>
                </a>
            </li>
        @endif

        {{-- สต็อก --}}
        @if(RoleHelper::canManageSystem())
            <li class="menu-header small text-uppercase"><span class="menu-header-text">สต็อก</span></li>
            <li class="menu-item {{ ($function_key == 'stock') ? 'active' : '' }}">
                <a href="{{ route('stock') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bxs-component"></i>
                    <div data-i18n="Basic">รายการสต็อก</div>
                </a>
            </li>
        @endif

        {{-- รายจ่าย - Owner และ Manager เท่านั้น --}}
        @if(in_array($userRole, ['owner', 'manager']))
            <li class="menu-header small text-uppercase"><span class="menu-header-text">รายจ่าย</span></li>

            <li class="menu-item {{ ($function_key == 'category_expenses') ? 'active' : '' }}">
                <a href="{{ route('category_expenses') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-collection"></i>
                    <div data-i18n="Basic">หมวดหมู่รายจ่าย</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'expenses') ? 'active' : '' }}">
                <a href="{{ route('expenses') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bxs-dollar-circle"></i>
                    <div data-i18n="Basic">รายจ่ายทั้งหมด</div>
                </a>
            </li>
        @endif

        {{-- สมาชิก --}}
        @if(RoleHelper::canManageSystem())
            <li class="menu-header small text-uppercase"><span class="menu-header-text">สมาชิก</span></li>

            <li class="menu-item {{ ($function_key == 'memberCategory') ? 'active' : '' }}">
                <a href="{{ route('memberCategory') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-collection"></i>
                    <div data-i18n="Analytics">หมวดหมู่สมาชิก</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'member') ? 'active' : '' }}">
                <a href="{{ route('member') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-user-pin"></i>
                    <div data-i18n="Analytics">สมาชิก</div>
                </a>
            </li>
        @endif

        {{-- ระบบ - Owner เท่านั้น --}}
        @if(RoleHelper::isOwner())
            <li class="menu-header small text-uppercase"><span class="menu-header-text">ระบบ</span></li>

            <li class="menu-item {{ ($function_key == 'admin.users') ? 'active' : '' }}">
                <a href="{{ route('admin.users') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-user-cog"></i>
                    <div data-i18n="Analytics">จัดการผู้ใช้</div>
                </a>
            </li>

            <li class="menu-item {{ ($function_key == 'config') ? 'active' : '' }}">
                <a href="{{ route('config') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-cog"></i>
                    <div data-i18n="Analytics">ตั้งค่าเว็บไซต์</div>
                </a>
            </li>
        @endif

    </ul>
</aside>
