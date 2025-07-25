<?php

// app/Helpers/MenuHelper.php
namespace App\Helpers;

use Illuminate\Support\Facades\Session;

class MenuHelper
{
    /**
     * ดึงเมนูทั้งหมดตามสิทธิ์ของผู้ใช้
     */
    public static function getMenuItems()
    {
        if (!Session::has('user')) {
            return [];
        }

        $user = Session::get('user');
        $userRole = $user->role ?? null;

        // แปลง admin เดิมเป็น owner
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }

        $menus = [
            // Dashboard - ทุกคนเข้าได้
            [
                'title' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => 'dashboard',
                'roles' => ['owner', 'manager', 'cashier', 'staff']
            ],
            
            // ส่วนออร์เดอร์
            [
                'title' => 'ออเดอร์หน้าร้าน',
                'icon' => 'fas fa-shopping-cart',
                'route' => 'Memberorder',
                'roles' => ['owner', 'manager', 'cashier', 'staff']
            ],
            [
                'title' => 'ออเดอร์ออนไลน์',
                'icon' => 'fas fa-truck',
                'route' => 'MemberorderRider',
                'roles' => ['owner', 'manager', 'cashier', 'staff']
            ],
            [
                'title' => 'จัดการออเดอร์',
                'icon' => 'fas fa-list-alt',
                'route' => 'adminorder',
                'roles' => ['owner', 'manager', 'cashier']
            ],
            
            // ส่วนจัดการข้อมูล
            [
                'title' => 'หมวดหมู่สินค้า',
                'icon' => 'fas fa-tags',
                'route' => 'category',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'เมนูอาหาร',
                'icon' => 'fas fa-utensils',
                'route' => 'menu',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'โปรโมชั่น',
                'icon' => 'fas fa-percent',
                'route' => 'promotion',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'จัดการโต้ะ',
                'icon' => 'fas fa-chair',
                'route' => 'table',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'ไรเดอร์',
                'icon' => 'fas fa-motorcycle',
                'route' => 'rider',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'สต็อกสินค้า',
                'icon' => 'fas fa-boxes',
                'route' => 'stock',
                'roles' => ['owner', 'manager']
            ],
            
            // ส่วนสมาชิก
            [
                'title' => 'หมวดหมู่สมาชิก',
                'icon' => 'fas fa-users-cog',
                'route' => 'memberCategory',
                'roles' => ['owner', 'manager']
            ],
            [
                'title' => 'สมาชิก',
                'icon' => 'fas fa-users',
                'route' => 'member',
                'roles' => ['owner', 'manager']
            ],
            
            // ส่วนการเงิน - เฉพาะ Owner และ Cashier
            [
                'title' => 'รายงานการขาย',
                'icon' => 'fas fa-chart-line',
                'route' => 'salesReport',
                'roles' => ['owner']
            ],
            [
                'title' => 'รายงานการเงิน',
                'icon' => 'fas fa-file-invoice-dollar',
                'route' => 'financialReport',
                'roles' => ['owner']
            ],
            [
                'title' => 'ยอดขายประจำวัน',
                'icon' => 'fas fa-cash-register',
                'route' => 'myDailySales',
                'roles' => ['cashier']
            ],
            
            // ส่วนรายจ่าย - เฉพาะ Owner,manager
            [
                'title' => 'หมวดหมู่รายจ่าย',
                'icon' => 'fas fa-list',
                'route' => 'category_expenses',
                'roles' => ['owner','manager']
            ],
            [
                'title' => 'รายจ่าย',
                'icon' => 'fas fa-money-bill-wave',
                'route' => 'expenses',
                'roles' => ['owner', 'manager']
            ],
            
            // ส่วนผู้ใช้ - เฉพาะ Owner
            [
                'title' => 'จัดการผู้ใช้',
                'icon' => 'fas fa-user-cog',
                'route' => 'admin.users',
                'roles' => ['owner']
            ],
            
            // ส่วนตั้งค่า - เฉพาะ Owner
            [
                'title' => 'ตั้งค่าระบบ',
                'icon' => 'fas fa-cog',
                'route' => 'config',
                'roles' => ['owner']
            ]
        ];

        // กรองเมนูตามสิทธิ์
        return array_filter($menus, function($menu) use ($userRole) {
            return in_array($userRole, $menu['roles']);
        });
    }

    /**
     * ตรวจสอบว่า user มีสิทธิ์เข้าถึงเมนูนี้หรือไม่
     */
    public static function hasAccess($routeName)
    {
        $menus = self::getMenuItems();
        
        foreach ($menus as $menu) {
            if ($menu['route'] === $routeName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * สร้าง HTML สำหรับเมนู
     */
    public static function renderMenu()
    {
        $menus = self::getMenuItems();
        $html = '';
        
        // จัดกลุ่มเมนู
        $groupedMenus = self::groupMenus($menus);
        
        foreach ($groupedMenus as $groupName => $groupMenus) {
            if ($groupName !== 'main') {
                $html .= '<div class="menu-header">' . $groupName . '</div>';
            }
            
            foreach ($groupMenus as $menu) {
                $active = request()->routeIs($menu['route']) ? 'active' : '';
                $html .= '
                    <li class="nav-item ' . $active . '">
                        <a class="nav-link" href="' . route($menu['route']) . '">
                            <i class="' . $menu['icon'] . '"></i>
                            <span>' . $menu['title'] . '</span>
                        </a>
                    </li>
                ';
            }
        }
        
        return $html;
    }

    /**
     * จัดกลุ่มเมนู
     */
    private static function groupMenus($menus)
    {
        $groups = [
            'main' => [],
            'จัดการข้อมูล' => [],
            'สมาชิก' => [],
            'การเงิน' => [],
            'ระบบ' => []
        ];

        foreach ($menus as $menu) {
            switch ($menu['route']) {
                case 'dashboard':
                case 'Memberorder':
                case 'MemberorderRider':
                case 'adminorder':
                    $groups['main'][] = $menu;
                    break;
                    
                case 'category':
                case 'menu':
                case 'promotion':
                case 'table':
                case 'rider':
                case 'stock':
                    $groups['จัดการข้อมูล'][] = $menu;
                    break;
                    
                case 'memberCategory':
                case 'member':
                    $groups['สมาชิก'][] = $menu;
                    break;
                    
                case 'salesReport':
                case 'financialReport':
                case 'myDailySales':
                case 'category_expenses':
                case 'expenses':
                    $groups['การเงิน'][] = $menu;
                    break;
                    
                case 'admin.users':
                case 'config':
                    $groups['ระบบ'][] = $menu;
                    break;
            }
        }

        // ลบกลุ่มที่ไม่มีเมนู
        return array_filter($groups, function($group) {
            return !empty($group);
        });
    }

    /**
     * ตรวจสอบ role ปัจจุบัน
     */
    public static function getCurrentRole()
    {
        if (!Session::has('user')) {
            return null;
        }

        $user = Session::get('user');
        $userRole = $user->role ?? null;

        if ($userRole === 'admin') {
            $userRole = 'owner';
        }

        return $userRole;
    }

    
    public static function getRoleName($role = null)
    {
        if (!$role) {
            $role = self::getCurrentRole();
        }

        $roleNames = [
            'owner' => 'เจ้าของร้าน',
            'manager' => 'ผู้จัดการ',
            'cashier' => 'แคชเชียร์',
            'staff' => 'พนักงาน',
            'user' => 'ลูกค้า'
        ];

        return $roleNames[$role] ?? $role;
    }
}