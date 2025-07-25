<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckFinancialAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ตรวจสอบว่ามี session user หรือไม่
        if (!Session::has('user')) {
            return redirect('/admin/login');
        }

        $user = Session::get('user');
        $userRole = $user->role ?? null;
        
        // เฉพาะ Owner และ Cashier เท่านั้นที่เห็นยอดเงิน
        if (!in_array($userRole, ['owner', 'cashier'])) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลทางการเงิน',
                    'message' => 'เฉพาะเจ้าของและแคชเชียร์เท่านั้นที่สามารถดูยอดเงินได้'
                ], 403);
            }
            
            abort(403, 'ไม่มีสิทธิ์เข้าถึงข้อมูลทางการเงิน');
        }

        return $next($request);
    }
}