<?php

namespace App\Http\Controllers\admin;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Config;
use App\Models\Menu;
use App\Models\MenuOption;
use App\Models\Orders;
use App\Models\OrdersDetails;
use App\Models\OrdersOption;
use App\Models\Pay;
use App\Models\PayGroup;
use App\Models\RiderSend;
use App\Models\Table;
use App\Models\User;
use BaconQrCode\Encoder\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use PromptPayQR\Builder;
use App\Helpers\RoleHelper;

class Admin extends Controller
{
    public function dashboard(Request $request)
    {
        $userRole = RoleHelper::getCurrentRole();
        $view = $request->get('view', 'default'); // default, sales, financial, daily-sales
        
        $data['function_key'] = __FUNCTION__;
        $data['view_type'] = $view;
        
        // ตรวจสอบสิทธิ์ตาม view
        switch ($view) {
            case 'sales':
            case 'financial':
                if (!RoleHelper::isOwner()) {
                    abort(403, 'เฉพาะเจ้าของเท่านั้น');
                }
                break;
            case 'daily-sales':
                if (!RoleHelper::isCashier()) {
                    abort(403, 'เฉพาะแคชเชียร์เท่านั้น');
                }
                break;
        }
        
        // ข้อมูลพื้นฐาน
        $data['ordertotal'] = Orders::count();
        $data['rider'] = User::where('role', 'staff')->get();
        $data['user_role'] = $userRole;
        $data['user_name'] = RoleHelper::getCurrentUser()->name ?? 'ผู้ใช้';
        $data['config'] = Config::first();

        // ข้อมูลตาม view และ role
        switch ($view) {
            case 'sales':
                $data = array_merge($data, $this->getSalesReportData());
                break;
            case 'financial':
                $data = array_merge($data, $this->getFinancialReportData());
                break;
            case 'daily-sales':
                $data = array_merge($data, $this->getDailySalesData());
                break;
            default:
                $data = array_merge($data, $this->getDefaultDashboardData($userRole));
                break;
        }
        
        return view('dashboard', $data);
    }

    /**
     * ข้อมูล Dashboard ปกติ
     */
    private function getDefaultDashboardData($userRole)
    {
        $data = [];
        
        // ข้อมูลที่แสดงตาม role
        if (RoleHelper::canViewAmount()) {
            // Owner และ Cashier เห็นยอดเงิน
            $data['orderday'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereDay('created_at', date('d'))->first();
            $data['ordermouth'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereMonth('created_at', date('m'))->first();
            $data['orderyear'] = Orders::select(DB::raw("SUM(total)as total"))->where('status', 3)->whereYear('created_at', date('Y'))->first();
        } else {
            // Manager และ Staff ไม่เห็นยอดเงิน
            $data['orderday'] = (object)['total' => null];
            $data['ordermouth'] = (object)['total' => null];
            $data['orderyear'] = (object)['total' => null];
        }

        // ข้อมูลเมนูยอดนิยม - ทุก role ดูได้
        $menu = Menu::select('id', 'name')->get();
        $item_menu = array();
        $item_order = array();
        if (count($menu) > 0) {
            foreach ($menu as $rs) {
                $item_menu[] = $rs->name;
                $menu_order = OrdersDetails::Join('orders', 'orders.id', '=', 'orders_details.order_id')
                    ->where('orders.status', 3)
                    ->where('menu_id', $rs->id)
                    ->groupBy('menu_id')
                    ->count();
                $item_order[] = $menu_order;
            }
        }

        // ข้อมูลรายเดือน - เฉพาะ Owner
        $item_mouth = array();
        if (RoleHelper::isOwner()) {
            for ($i = 1; $i < 13; $i++) {
                $query = Orders::select(DB::raw("SUM(total)as total"))
                    ->where('status', 3)
                    ->whereMonth('created_at', date($i))
                    ->first();
                $item_mouth[] = $query->total ?? 0;
            }
        }

        $data['item_menu'] = $item_menu;
        $data['item_order'] = $item_order;
        $data['item_mouth'] = $item_mouth;
        
        return $data;
    }

    /**
     * ข้อมูลรายงานการขาย - เฉพาะ Owner
     */
    private function getSalesReportData()
    {
        $data = [];
        
        // ยอดขายวันนี้
        $data['today_sales'] = Orders::where('status', 3)
            ->whereDate('created_at', today())
            ->sum('total');
            
        // ยอดขายเดือนนี้
        $data['month_sales'] = Orders::where('status', 3)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->sum('total');
            
        // ยอดขายปีนี้
        $data['year_sales'] = Orders::where('status', 3)
            ->whereYear('created_at', date('Y'))
            ->sum('total');

        // สินค้าขายดี Top 10
        $data['top_products'] = OrdersDetails::select('menu_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(price * quantity) as total_amount'))
            ->join('orders', 'orders.id', '=', 'orders_details.order_id')
            ->join('menus', 'menus.id', '=', 'orders_details.menu_id')
            ->where('orders.status', 3)
            ->groupBy('menu_id')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->with('menu')
            ->get();

        // ข้อมูลรายวัน 7 วันล่าสุด
        $data['daily_chart'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $sales = Orders::where('status', 3)
                ->whereDate('created_at', $date)
                ->sum('total');
            $data['daily_chart'][] = [
                'date' => $date->format('d/m'),
                'sales' => $sales
            ];
        }
        
        return $data;
    }

    /**
     * ข้อมูลรายงานการเงิน - เฉพาะ Owner
     */
    private function getFinancialReportData()
    {
        $data = [];
        
        // รายรับ
        $data['total_income'] = Orders::where('status', 3)->sum('total');
        $data['month_income'] = Orders::where('status', 3)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->sum('total');
            
        // รายจ่าย (ถ้ามี model Expenses)
        // $data['total_expenses'] = Expenses::sum('amount');
        // $data['month_expenses'] = Expenses::whereMonth('created_at', date('m'))->sum('amount');
        $data['total_expenses'] = 0; // ชั่วคราว
        $data['month_expenses'] = 0; // ชั่วคราว
        
        // กำไร
        $data['total_profit'] = $data['total_income'] - $data['total_expenses'];
        $data['month_profit'] = $data['month_income'] - $data['month_expenses'];
        
        // จำนวนออร์เดอร์
        $data['total_orders'] = Orders::where('status', 3)->count();
        $data['month_orders'] = Orders::where('status', 3)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->count();
            
        // ออร์เดอร์เฉลี่ยต่อวัน
        $data['avg_daily_orders'] = round($data['month_orders'] / date('j'), 2);
        
        // ยอดเฉลี่ยต่อออร์เดอร์
        $data['avg_order_amount'] = $data['total_orders'] > 0 ? round($data['total_income'] / $data['total_orders'], 2) : 0;
        
        return $data;
    }

    /**
     * ข้อมูลยอดขายประจำวัน - เฉพาะ Cashier
     */
    private function getDailySalesData()
    {
        $data = [];
        $userId = Session::get('user')->id;
        
        // ยอดขายวันนี้ของแคชเชียร์คนนี้
        $data['my_today_sales'] = Pay::where('processed_by', $userId)
            ->whereDate('created_at', today())
            ->sum('total');
            
        // จำนวนบิลวันนี้
        $data['my_today_bills'] = Pay::where('processed_by', $userId)
            ->whereDate('created_at', today())
            ->count();
            
        // เวลาเริ่มงาน
        $data['shift_start'] = Pay::where('processed_by', $userId)
            ->whereDate('created_at', today())
            ->min('created_at');
            
        // รายการชำระเงินวันนี้
        $data['today_payments'] = Pay::where('processed_by', $userId)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return $data;
    }

    public function order()
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        $data['function_key'] = 'order';
        $data['rider'] = User::where('role', 'staff')->get();
        $data['config'] = Config::first();
        $data['can_view_amount'] = RoleHelper::canViewAmount();
        
        return view('order', $data);
    }

    public function ListOrder()
    {
        // ตรวจสอบสิทธิ์ - เฉพาะ Owner และ Cashier เห็นยอดเงิน
        if (!RoleHelper::canViewAmount()) {
            return $this->ListOrderForManager();
        }

        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];

        $order = DB::table('orders as o')
            ->select(
                'o.table_id',
                DB::raw('SUM(o.total) as total'),
                DB::raw('MAX(o.created_at) as created_at'),
                DB::raw('MAX(o.status) as status'),
                DB::raw('MAX(o.remark) as remark'),
                DB::raw('SUM(CASE WHEN o.status = 1 THEN 1 ELSE 0 END) as has_status_1')
            )
            ->whereNotNull('o.table_id')
            ->whereIn('o.status', [1, 2])
            ->groupBy('o.table_id')
            ->orderByDesc('has_status_1')
            ->orderByDesc(DB::raw('MAX(o.created_at)'))
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                
                if ($rs->has_status_1 > 0) {
                    $status = '<button type="button" class="btn btn-sm btn-primary update-status" data-id="' . $rs->table_id . '">กำลังทำอาหาร</button>';
                } else {
                    $status = '<button class="btn btn-sm btn-success">ออเดอร์สำเร็จแล้ว</button>';
                }

                if ($rs->status != 3) {
                    $pay = '<a href="' . route('printOrderAdmin', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์</a>
                    <a href="' . route('printOrderAdminCook', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์ในครัว</a>';
                    
                    // เฉพาะ Owner และ Cashier เห็นปุ่มชำระเงิน
                    if (RoleHelper::canManageFinance()) {
                        $pay .= '<button data-id="' . $rs->table_id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-success modalPay">ชำระเงิน</button>';
                    }
                }
                
                $flag_order = '<button class="btn btn-sm btn-success">สั่งหน้าร้าน</button>';
                $action = '<button data-id="' . $rs->table_id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                $table = Table::find($rs->table_id);
                
                $info[] = [
                    'flag_order' => $flag_order,
                    'table_id' => $table->table_number,
                    'total' => $rs->total, // แสดงยอดเงิน
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    /**
     * สำหรับ Manager - ไม่แสดงยอดเงิน
     */
    public function ListOrderForManager()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];

        $order = DB::table('orders as o')
            ->select(
                'o.table_id',
                DB::raw('MAX(o.created_at) as created_at'),
                DB::raw('MAX(o.status) as status'),
                DB::raw('MAX(o.remark) as remark'),
                DB::raw('SUM(CASE WHEN o.status = 1 THEN 1 ELSE 0 END) as has_status_1')
            )
            ->whereNotNull('o.table_id')
            ->whereIn('o.status', [1, 2])
            ->groupBy('o.table_id')
            ->orderByDesc('has_status_1')
            ->orderByDesc(DB::raw('MAX(o.created_at)'))
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                
                if ($rs->has_status_1 > 0) {
                    $status = '<button type="button" class="btn btn-sm btn-primary update-status" data-id="' . $rs->table_id . '">กำลังทำอาหาร</button>';
                } else {
                    $status = '<button class="btn btn-sm btn-success">ออเดอร์สำเร็จแล้ว</button>';
                }

                if ($rs->status != 3) {
                    $pay = '<a href="' . route('printOrderAdmin', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์</a>
                    <a href="' . route('printOrderAdminCook', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์ในครัว</a>';
                    // Manager ไม่เห็นปุ่มชำระเงิน
                }
                
                $flag_order = '<button class="btn btn-sm btn-success">สั่งหน้าร้าน</button>';
                $action = '<button data-id="' . $rs->table_id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                $table = Table::find($rs->table_id);
                
                $info[] = [
                    'flag_order' => $flag_order,
                    'table_id' => $table->table_number,
                    'total' => '***', // ซ่อนยอดเงิน
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    // เพิ่ม Method สำหรับจัดการผู้ใช้ (เฉพาะ Owner)
    public function users()
    {
        if (!RoleHelper::isOwner()) {
            abort(403, 'เฉพาะเจ้าของเท่านั้น');
        }

        $data['function_key'] = 'admin.users';
        $data['users'] = User::where('role', '!=', 'user')->get();
        return view('admin.users.index', $data);
    }

    public function usersSave(Request $request)
    {
        if (!RoleHelper::isOwner()) {
            abort(403, 'เฉพาะเจ้าของเท่านั้น');
        }

        // บันทึกข้อมูลผู้ใช้
        // ... code สำหรับบันทึก
    }

    // ฟังก์ชันเดิมที่เหลือ...
    function DateThai($strDate)
    {
        $strYear = date("Y", strtotime($strDate)) + 543;
        $strMonth = date("n", strtotime($strDate));
        $strDay = date("j", strtotime($strDate));
        $time = date("H:i", strtotime($strDate));
        $strMonthCut = array("", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม");
        $strMonthThai = $strMonthCut[$strMonth];
        return "$strDay $strMonthThai $strYear" . " " . $time;
    }

    function generateRunningNumber($prefix = '', $padLength = 7)
    {
        $latest = Pay::orderBy('id', 'desc')->first();

        if ($latest && isset($latest->payment_number)) {
            $number = (int) ltrim($latest->payment_number, '0');
            $next = $number + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, $padLength, '0', STR_PAD_LEFT);
    }

    // เพิ่มฟังก์ชันอื่นๆ ที่ขาดไป...
    public function listOrderDetail(Request $request)
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $orders = Orders::where('table_id', $request->input('id'))
            ->whereIn('status', [1, 2])
            ->get();
        $info = '';
        foreach ($orders as $order) {
            $info .= '<div class="mb-3">';
            $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $order->id . '</h5></div>
            <div class="col-auto d-flex align-items-start">';
            
            if ($order->status != 2) {
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-primary updatestatusOrder m-1" data-id="' . $order->id . '">อัพเดทออเดอร์สำเร็จแล้ว</button>';
                
                // เฉพาะ Owner และ Manager ยกเลิกออร์เดอร์ได้
                if (RoleHelper::canCancelOrder()) {
                    $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelOrderSwal m-1" data-id="' . $order->id . '">ยกเลิกออเดอร์</button>';
                }
            }
            $info .= '</div></div>';
            
            $orderDetails = OrdersDetails::where('order_id', $order->id)->get()->groupBy('menu_id');
            foreach ($orderDetails as $details) {
                $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();
                
                foreach ($details as $detail) {
                    $detailsText = [];
                    if ($orderOption->isNotEmpty()) {
                        foreach ($orderOption as $key => $option) {
                            $optionName = MenuOption::find($option->option_id);
                            $detailsText[] = $optionName->type;
                        }
                        $detailsText = implode(',', $detailsText);
                    }
                    $optionType = $menuName;
                    
                    // แสดงราคาตามสิทธิ์
                    if (RoleHelper::canViewAmount()) {
                        $priceTotal = number_format($detail->price, 2);
                        $priceButton = '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
                    } else {
                        $priceButton = '<button class="btn btn-sm btn-secondary me-1">***</button>';
                    }
                    
                    $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                    $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
                    $info .= '<div class="flex-grow-1">';
                    $info .= '<div><span class="fw-bold">' . htmlspecialchars($optionType) . '</span></div>';
                    if (!empty($detailsText)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . $detailsText . '</div>';
                    }
                    if (!empty($detail->remark)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
                    }
                    $info .= '</div>';
                    $info .= '<div class="text-end d-flex flex-column align-items-end">';
                    $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
                    $info .= '<div>';
                    $info .= $priceButton;
                    
                    // เฉพาะ Owner และ Manager ยกเลิกเมนูได้
                    if (RoleHelper::canCancelOrder()) {
                        $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelMenuSwal" data-id="' . $detail->id . '">ยกเลิก</button>';
                    }
                    $info .= '</div>';
                    $info .= '</div>';
                    $info .= '</li>';
                    $info .= '</ul>';
                }
            }
            $info .= '</div>';
        }
        echo $info;
    }

    public function config()
    {
        // เฉพาะ Owner เข้าได้
        if (!RoleHelper::isOwner()) {
            abort(403, 'เฉพาะเจ้าของเท่านั้น');
        }

        $data['function_key'] = __FUNCTION__;
        $data['config'] = Config::first();
        return view('config', $data);
    }

    public function ConfigSave(Request $request)
    {
        // เฉพาะ Owner เข้าได้
        if (!RoleHelper::isOwner()) {
            abort(403, 'เฉพาะเจ้าของเท่านั้น');
        }

        $input = $request->input();
        $config = Config::find($input['id']);
        $config->name = $input['name'];
        $config->color1 = $input['color1'];
        $config->color2 = $input['color2'];
        $config->color_font = $input['color_font'];
        $config->color_category = $input['color_category'];
        $config->promptpay = $input['promptpay'];

        if ($request->hasFile('image_bg')) {
            $file = $request->file('image_bg');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_bg = $path;
        }
        if ($request->hasFile('image_qr')) {
            $file = $request->file('image_qr');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_qr = $path;
        }
        if ($config->save()) {
            return redirect()->route('config')->with('success', 'บันทึกรายการเรียบร้อยแล้ว');
        }
        return redirect()->route('config')->with('error', 'ไม่สามารถบันทึกข้อมูลได้');
    }

    public function confirm_pay(Request $request)
    {
        // เฉพาะ Owner และ Cashier จัดการการเงินได้
        if (!RoleHelper::canManageFinance()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์จัดการการเงิน'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'ชำระเงินไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $total = DB::table('orders as o')
                ->select(
                    'o.table_id',
                    DB::raw('SUM(o.total) as total'),
                )
                ->whereNotNull('table_id')
                ->groupBy('o.table_id')
                ->where('table_id', $id)
                ->whereIn('status', [1, 2])
                ->first();
                
            $pay = new Pay();
            $pay->payment_number = $this->generateRunningNumber();
            $pay->table_id = $id;
            $pay->total = $total->total;
            $pay->processed_by = Session::get('user')->id; // เก็บว่าใครชำระ
            
            if ($pay->save()) {
                $order = Orders::where('table_id', $id)->whereIn('status', [1, 2])->get();
                foreach ($order as $rs) {
                    $rs->status = 3;
                    if ($rs->save()) {
                        $paygroup = new PayGroup();
                        $paygroup->pay_id = $pay->id;
                        $paygroup->order_id = $rs->id;
                        $paygroup->save();
                    }
                }
                $data = [
                    'status' => true,
                    'message' => 'ชำระเงินเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function cancelOrder(Request $request)
    {
        // เฉพาะ Owner และ Manager ยกเลิกออร์เดอร์ได้
        if (!RoleHelper::canCancelOrder()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์ยกเลิกออร์เดอร์'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = Orders::where('id', $id)->first();
            if ($menu->delete()) {
                $order = OrdersDetails::where('order_id', $id)->delete();
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function cancelMenu(Request $request)
    {
        // เฉพาะ Owner และ Manager ยกเลิกเมนูได้
        if (!RoleHelper::canCancelOrder()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์ยกเลิกเมนู'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = OrdersDetails::where('id', $id)->first();
            $count = OrdersDetails::where('order_id', $menu->order_id)->count();
            $total = $menu->price * $menu->quantity;
            if ($menu->delete()) {
                if ($count == 1) {
                    $order = Orders::where('id', $menu->order_id)->delete();
                } else {
                    $order = Orders::where('id', $menu->order_id)->first();
                    $order->total = $order->total - $total;
                    $order->save();
                }
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function generateQr(Request $request)
    {
        // เฉพาะ Owner และ Cashier
        if (!RoleHelper::canManageFinance()) {
            return response()->json(['error' => 'ไม่มีสิทธิ์'], 403);
        }

        $config = Config::first();
        if ($config->promptpay != '') {
            $total = $request->total;
            $qr = Builder::staticMerchantPresentedQR($config->promptpay)->setAmount($total)->toSvgString();
            echo '<div class="row g-3 mb-3">
                <div class="col-md-12">
                    ' . $qr . '
                </div>
            </div>';
        } elseif ($config->image_qr != '') {
            echo '
        <div class="row g-3 mb-3">
            <div class="col-md-12">
            <img width="100%" src="' . url('storage/' . $config->image_qr) . '">
            </div>
        </div>';
        }
    }

    public function confirm_rider(Request $request)
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canManageSystem()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์จัดการไรเดอร์'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'ส่งข้อมูลไปยังไรเดอร์ไม่สำเร็จ',
        ];
        $input = $request->input();
        if ($input['id']) {
            $order = Orders::find($input['id']);
            $order->status = 2;
            if ($order->save()) {
                $rider_save = new RiderSend();
                $rider_save->order_id = $input['id'];
                $rider_save->rider_id = $input['rider_id'];
                if ($rider_save->save()) {
                    $data = [
                        'status' => true,
                        'message' => 'ส่งข้อมูลไปยังไรเดอร์เรียบร้อยแล้ว',
                    ];
                }
            }
        }
        return response()->json($data);
    }

    public function order_rider()
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        $data['function_key'] = 'order_rider';
        $data['rider'] = User::where('role', 'staff')->get();
        $data['config'] = Config::first();
        $data['can_view_amount'] = RoleHelper::canViewAmount();
        return view('order_rider', $data);
    }

    public function ListOrderRider()
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์เข้าถึง'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $order = Orders::select('orders.*', 'users.name')
            ->join('users', 'orders.users_id', '=', 'users.id')
            ->whereNull('table_id')
            ->whereNotNull('users_id')
            ->whereNotNull('address_id')
            ->orderBy('created_at', 'desc')
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                if ($rs->status == 1) {
                    $status = '<button class="btn btn-sm btn-primary">กำลังทำอาหาร</button>';
                }
                if ($rs->status == 2) {
                    $status = '<button class="btn btn-sm btn-success">กำลังจัดส่ง</button>';
                }
                if ($rs->status == 3) {
                    $status = '<button class="btn btn-sm btn-success">ชำระเงินเรียบร้อยแล้ว</button>';
                }

                if ($rs->status == 1) {
                    $pay = '<button data-id="' . $rs->id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-warning modalRider">จัดส่ง</button>';
                }
                $flag_order = '<button class="btn btn-sm btn-warning">สั่งออนไลน์</button>';
                $action = '<button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                
                // แสดงยอดเงินตามสิทธิ์
                $total = RoleHelper::canViewAmount() ? $rs->total : '***';
                
                $info[] = [
                    'flag_order' => $flag_order,
                    'name' => $rs->name,
                    'total' => $total,
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function listOrderDetailRider(Request $request)
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $orderId = $request->input('id');
        $order = Orders::find($orderId);
        $info = '';

        if ($order) {
            $orderDetails = OrdersDetails::where('order_id', $orderId)->get()->groupBy('menu_id');
            $info .= '<div class="mb-3">';
            $info .= '<div class="row">';
            $info .= '<div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $orderId . '</h5></div>';
            $info .= '<div class="col-auto d-flex align-items-start">';

            if ($order->status != 2 && RoleHelper::canCancelOrder()) {
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelOrderSwal m-1" data-id="' . $orderId . '">ยกเลิกออเดอร์</button>';
            }

            $info .= '</div></div>';

            foreach ($orderDetails as $details) {
                $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();

                $detailsText = [];
                if ($orderOption->isNotEmpty()) {
                    foreach ($orderOption as $option) {
                        $optionName = MenuOption::find($option->option_id);
                        $detailsText[] = $optionName->type;
                    }
                }

                foreach ($details as $detail) {
                    // แสดงราคาตามสิทธิ์
                    if (RoleHelper::canViewAmount()) {
                        $priceTotal = number_format($detail->price, 2);
                        $priceButton = '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
                    } else {
                        $priceButton = '<button class="btn btn-sm btn-secondary me-1">***</button>';
                    }
                    
                    $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                    $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
                    $info .= '<div class="flex-grow-1">';
                    $info .= '<div><span class="fw-bold">' . htmlspecialchars($menuName) . '</span></div>';

                    if (!empty($detailsText)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . implode(',', $detailsText) . '</div>';
                    }
                    if (!empty($detail->remark)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
                    }
                    $info .= '</div>';
                    $info .= '<div class="text-end d-flex flex-column align-items-end">';
                    $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
                    $info .= '<div>';
                    $info .= $priceButton;
                    
                    if (RoleHelper::canCancelOrder()) {
                        $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelMenuSwal" data-id="' . $detail->id . '">ยกเลิก</button>';
                    }
                    $info .= '</div>';
                    $info .= '</div>';
                    $info .= '</li>';
                    $info .= '</ul>';
                }
            }

            $info .= '</div>';
        }

        echo $info;
    }

    public function ListOrderPay()
    {
        // เฉพาะ Owner และ Cashier เห็นข้อมูลการเงิน
        if (!RoleHelper::canViewAmount()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์ดูข้อมูลการเงิน'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $pay = Pay::whereNotNull('table_id')->get();

        if (count($pay) > 0) {
            $info = [];
            foreach ($pay as $rs) {
                $action = '<a href="' . route('printReceipt', $rs->id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ออกใบเสร็จฉบับย่อ</a>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalTax m-1">ออกใบกำกับภาษี</button>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShowPay m-1">รายละเอียด</button>';
                $table = Table::find($rs->table_id);
                $info[] = [
                    'payment_number' => $rs->payment_number,
                    'table_id' => $table->table_number,
                    'total' => $rs->total,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function ListOrderPayRider()
    {
        // เฉพาะ Owner และ Cashier เห็นข้อมูลการเงิน
        if (!RoleHelper::canViewAmount()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์ดูข้อมูลการเงิน'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $pay = Pay::whereNull('table_id')->get();

        if (count($pay) > 0) {
            $info = [];
            foreach ($pay as $rs) {
                $action = '<a href="' . route('printReceipt', $rs->id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ออกใบเสร็จฉบับย่อ</a>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalTax m-1">ออกใบกำกับภาษี</button>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShowPay m-1">รายละเอียด</button>';
                $info[] = [
                    'payment_number' => $rs->payment_number,
                    'table_id' => $rs->table_id,
                    'total' => $rs->total,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function listOrderDetailPay(Request $request)
    {
        // เฉพาะ Owner และ Cashier เห็นข้อมูลการเงิน
        if (!RoleHelper::canViewAmount()) {
            return response()->json(['error' => 'ไม่มีสิทธิ์ดูข้อมูลการเงิน'], 403);
        }

        $paygroup = PayGroup::where('pay_id', $request->input('id'))->get();
        $info = '';
        foreach ($paygroup as $pg) {
            $orderDetailsGrouped = OrdersDetails::where('order_id', $pg->order_id)
                ->with('menu', 'option')
                ->get()
                ->groupBy('menu_id');
            if ($orderDetailsGrouped->isNotEmpty()) {
                $info .= '<div class="mb-3">';
                $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $pg->order_id . '</h5></div></div>';
                foreach ($orderDetailsGrouped as $details) {
                    $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                    $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();
                    foreach ($details as $detail) {
                        $detailsText = [];
                        if ($orderOption->isNotEmpty()) {
                            foreach ($orderOption as $key => $option) {
                                $optionName = MenuOption::find($option->option_id);
                                $detailsText[] = $optionName->type;
                            }
                            $detailsText = implode(',', $detailsText);
                        }
                        $optionType = $menuName;
                        $priceTotal = number_format($detail->price, 2);
                        $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                        $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
                        $info .= '<div class="flex-grow-1">';
                        $info .= '<div><span class="fw-bold">' . htmlspecialchars($optionType) . '</span></div>';
                        if (!empty($detailsText)) {
                            $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . $detailsText . '</div>';
                        }
                        if (!empty($detail->remark)) {
                            $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
                        }
                        $info .= '</div>';
                        $info .= '<div class="text-end d-flex flex-column align-items-end">';
                        $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
                        $info .= '<div>';
                        $info .= '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
                        $info .= '</div>';
                        $info .= '</div>';
                        $info .= '</li>';
                        $info .= '</ul>';
                    }
                }
                $info .= '</div>';
            }
        }
        echo $info;
    }

    public function printReceipt($id)
    {
        // เฉพาะ Owner และ Cashier
        if (!RoleHelper::canViewAmount()) {
            abort(403, 'ไม่มีสิทธิ์ดูใบเสร็จ');
        }

        $config = Config::first();
        $pay = Pay::find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();
        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }
        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option.option')
            ->get();
        return view('tax', compact('config', 'pay', 'order'));
    }

    public function printReceiptfull($id)
    {
        // เฉพาะ Owner และ Cashier
        if (!RoleHelper::canViewAmount()) {
            abort(403, 'ไม่มีสิทธิ์ดูใบเสร็จ');
        }

        $get = $_GET;
        $config = Config::first();
        $pay = Pay::find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();
        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }
        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option.option')
            ->get();
        return view('taxfull', compact('config', 'pay', 'order', 'get'));
    }

    public function updatestatus(Request $request)
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์อัพเดทสถานะ'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::where('table_id', $id)->get();
            foreach ($order as $rs) {
                $rs->status = 2;
                $rs->save();
            }
            $data = [
                'status' => true,
                'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
            ];
        }
        return response()->json($data);
    }

    public function updatestatusOrder(Request $request)
    {
        // ตรวจสอบสิทธิ์
        if (!RoleHelper::canReceiveOrders()) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่มีสิทธิ์อัพเดทสถานะ'
            ], 403);
        }

        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::find($id);
            $order->status = 2;
            if ($order->save()) {
                $data = [
                    'status' => true,
                    'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }
}