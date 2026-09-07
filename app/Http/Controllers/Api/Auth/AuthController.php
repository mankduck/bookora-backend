<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request
    ): JsonResponse {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make(
                    $request->password
                ),
                'status' => 'active',
            ]);

            $customerRole = Role::where(
                'code',
                'customer'
            )->firstOrFail();

            $user->roles()->attach(
                $customerRole->id
            );

            return $user;
        });

        /*
        |--------------------------------------------------------------------------
        | Đăng nhập luôn sau khi đăng ký
        |--------------------------------------------------------------------------
        */

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công.',
            'data' => [
                'user' => $user->load('roles'),
            ],
        ], 201);
    }

    public function login(
        LoginRequest $request
    ): JsonResponse {
        $login = $request->input('login');

        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra tài khoản + mật khẩu
        |--------------------------------------------------------------------------
        */

        if (
            !$user ||
            !Hash::check(
                $request->input('password'),
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'login' => [
                    'Thông tin đăng nhập không chính xác.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra trạng thái tài khoản
        |--------------------------------------------------------------------------
        */

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tài khoản hiện không hoạt động.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | QUAN TRỌNG
        |--------------------------------------------------------------------------
        |
        | Đây là phần trước đó còn thiếu.
        |
        | Auth::login() sẽ ghi user ID vào Laravel session.
        | Sau đó Sanctum SPA mới xác định được user ở request tiếp theo.
        |
        */

        Auth::login($user);

        /*
        |--------------------------------------------------------------------------
        | Regenerate Session ID
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | Cập nhật thời gian đăng nhập
        |--------------------------------------------------------------------------
        */

        $user->update([
            'last_login_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => $user
                    ->fresh()
                    ->load('roles'),
            ],
        ]);
    }

    public function me(
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request
                    ->user()
                    ->load('roles'),
            ],
        ]);
    }

    public function logout(
        Request $request
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Logout Laravel session
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->logout();

        /*
        |--------------------------------------------------------------------------
        | Hủy session cũ
        |--------------------------------------------------------------------------
        */

        $request->session()->invalidate();

        /*
        |--------------------------------------------------------------------------
        | Tạo CSRF token mới
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }
}