<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // ตรวจสอบว่ามี session user หรือไม่
        if (!Session::has('user')) {
            return $this->redirectToLogin($request);
        }

        $user = Session::get('user');
        $userRole = $user->role ?? null;

        // แปลง admin เดิมเป็น owner (backward compatibility)
        if ($userRole === 'admin') {
            $userRole = 'owner';
        }

        // ตรวจสอบว่า role ของ user ตรงกับที่กำหนดหรือไม่
        if (!in_array($userRole, $roles)) {
            // ถ้าเป็น AJAX request ส่ง JSON response
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'ไม่มีสิทธิ์เข้าถึง',
                    'message' => "Role '{$userRole}' ไม่มีสิทธิ์เข้าถึงหน้านี้",
                    'required_roles' => $roles,
                    'user_role' => $userRole
                ], 403);
            }

            // ถ้าไม่ใช่ AJAX แสดง 403 error
            abort(403, "ไม่มีสิทธิ์เข้าถึง - ต้องการ Role: " . implode(', ', $roles) . " แต่คุณเป็น: {$userRole}");
        }

        return $next($request);
    }

    /**
     * Redirect ไปหน้า login ตาม path
     */
    private function redirectToLogin(Request $request)
    {
        $requestUri = $request->getRequestUri();
        
        // ถ้าเป็นหน้า admin
        if ($requestUri === '/admin' || strpos($requestUri, '/admin') === 0) {
            return redirect('/admin/login')->with('error', 'กรุณาเข้าสู่ระบบ');
        }
        
        // ถ้าเป็นหน้า delivery
        if (strpos($requestUri, '/delivery') === 0) {
            return redirect('/delivery/login')->with('error', 'กรุณาเข้าสู่ระบบ');
        }
        
        // default
        return redirect('/admin/login')->with('error', 'กรุณาเข้าสู่ระบบ');
    }
}