<?php

// app/Helpers/RoleHelper.php
namespace App\Helpers;

use Illuminate\Support\Facades\Session;

class RoleHelper
{
    /**
     * ตรวจสอบว่าผู้ใช้เห็นยอดเงินได้หรือไม่
     */
    public static function canViewAmount()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        // แปลง admin เดิมเป็น owner
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }
        
        return in_array($userRole, ['owner', 'cashier', 'manager']);
    }
    
    /**
     * ตรวจสอบว่าเป็น Owner หรือไม่
     */
    public static function isOwner()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        return $userRole === 'owner' || $userRole === 'admin';
    }
    
    /**
     * ตรวจสอบว่าเป็น Manager หรือไม่
     */
    public static function isManager()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        return ($user->role ?? null) === 'manager';
    }
    
    /**
     * ตรวจสอบว่าเป็น Cashier หรือไม่
     */
    public static function isCashier()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        return ($user->role ?? null) === 'cashier';
    }
    
    /**
     * ตรวจสอบว่าเป็น Staff หรือไม่
     */
    public static function isStaff()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        return ($user->role ?? null) === 'staff';
    }
    
    /**
     * ตรวจสอบว่าเป็น User ทั่วไป หรือไม่
     */
    public static function isUser()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        return ($user->role ?? null) === 'user';
    }
    
    /**
     * ตรวจสอบว่าสามารถยกเลิกออร์เดอร์ได้หรือไม่
     */
    public static function canCancelOrder()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        // แปลง admin เดิมเป็น owner
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }
        
        return in_array($userRole, ['owner', 'manager']);
    }
    
    /**
     * ตรวจสอบว่าสามารถจัดการระบบได้หรือไม่
     */
    public static function canManageSystem()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }
        
        return in_array($userRole, ['owner', 'manager']);
    }
    
    
    public static function canViewReports()
    {
        return self::isOwner();
    }
    
    
    public static function canManageFinance()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }
        
        return in_array($userRole, ['owner', 'cashier', 'manager']);
    }
    
    
    public static function canReceiveOrders()
    {
        if (!Session::has('user')) {
            return false;
        }
        
        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }
        
        return in_array($userRole, ['owner', 'manager', 'cashier', 'staff']);
    }
    
 
    public static function hideAmount($amount, $showAsterisk = true)
    {
        if (self::canViewAmount()) {
            return number_format($amount, 2);
        }
        
        return $showAsterisk ? '***' : '';
    }
    
  
    public static function hideAmountHtml($amount, $currency = '฿')
    {
        if (self::canViewAmount()) {
            return '<span class="amount">' . $currency . ' ' . number_format($amount, 2) . '</span>';
        }
        
        return '<span class="amount-hidden" title="ไม่มีสิทธิ์ดูยอดเงิน">***</span>';
    }
    
    
    public static function getOrderColumns()
    {
        $columns = [
            'order_number' => 'เลขที่ออร์เดอร์',
            'table' => 'โต้ะ',
            'customer' => 'ลูกค้า',
            'items' => 'รายการ',
            'status' => 'สถานะ',
            'created_at' => 'เวลา'
        ];
        
        if (self::canViewAmount()) {
            $columns['total_amount'] = 'ยอดรวม';
            $columns['paid_amount'] = 'ยอดที่ชำระ';
        }
        
        $columns['actions'] = 'จัดการ';
        
        return $columns;
    }
    
    public static function getCurrentUser()
    {
        if (!Session::has('user')) {
            return null;
        }
        
        return Session::get('user');
    }
    
    
    public static function getCurrentRole()
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return null;
        }
        
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
    
    
    public static function hasPermission($permission)
    {
        $userRole = self::getCurrentRole();
        
        if (!$userRole) {
            return false;
        }
        
        $permissions = [
            'owner' => ['all'], 
            'manager' => [
                'cancel_order',
                'manage_menu',
                'manage_category',
                'manage_table',
                'manage_rider',
                'manage_stock',
                'manage_promotion',
                'manage_member',
                'view_order_list',
                'view_financial_reports',
                'view_daily_sales',
                'manage_expenses'
            ],
            'cashier' => [
                'handle_payment',
                'receive_order',
                'print_receipt',
                'view_amount',
                'view_daily_sales',
                'confirm_payment'
            ],
            'staff' => [
                'receive_order',
                'update_cooking_status',
                'print_kitchen_order',
                'view_order_status'
            ],
            'user' => [
                'place_order',
                'view_menu',
                'manage_address'
            ]
        ];
        
        $userPermissions = $permissions[$userRole] ?? [];
        
        return in_array('all', $userPermissions) || in_array($permission, $userPermissions);
    }
    
    
    public static function getRoleBadge($role = null)
    {
        if (!$role) {
            $role = self::getCurrentRole();
        }
        
        $badges = [
            'owner' => '<span class="badge badge-danger">เจ้าของร้าน</span>',
            'manager' => '<span class="badge badge-warning">ผู้จัดการ</span>',
            'cashier' => '<span class="badge badge-info">แคชเชียร์</span>',
            'staff' => '<span class="badge badge-secondary">พนักงาน</span>',
            'user' => '<span class="badge badge-primary">ลูกค้า</span>'
        ];
        
        return $badges[$role] ?? '<span class="badge badge-light">' . $role . '</span>';
    }
    
    
    public static function shouldHideAmountColumns()
    {
        return !self::canViewAmount();
    }
    
   
    public static function getAccessibleRoutes()
{
    $userRole = self::getCurrentRole();
    
    $routes = [
        'owner' => ['*'], 
        'manager' => [
            'adminorder', 'category', 'menu', 'promotion', 
            'table', 'rider', 'stock', 'member', 'memberCategory', 
            'Memberorder', 'MemberorderRider', 'category_expenses', 
            'expenses'  
        ],
        'cashier' => [
            'dashboard', 'adminorder', 'Memberorder', 'MemberorderRider',
            'myDailySales'
        ],
        'staff' => [
            'dashboard', 'Memberorder', 'MemberorderRider'
        ],
        'user' => [
            'delivery.users', 'delivery.order', 'delivery.listorder'
        ]
    ];
    
    return $routes[$userRole] ?? [];
}
    
  
    public static function canAccessRoute($routeName)
    {
        $accessibleRoutes = self::getAccessibleRoutes();
        
        if (in_array('*', $accessibleRoutes)) {
            return true;
        }
        
        return in_array($routeName, $accessibleRoutes);
    }

public static function canManagerViewFinance()
{
    if (!Session::has('user')) {
        return false;
    }
    
    $user = Session::get('user');
    $userRole = $user->role ?? null;
    
    if ($userRole === 'admin') {
        $userRole = 'owner';
    }
    
    return in_array($userRole, ['owner', 'manager']);
}

/**
 * ตรวจสอบว่าสามารถดูรายงานการเงินได้หรือไม่ (รวม Manager)
 */
public static function canViewFinancialReports()
{
    return self::canManagerViewFinance();
}

}