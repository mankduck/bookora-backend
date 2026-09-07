<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $user->load('roles:id,name,code');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    public function update(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique(
                    'users',
                    'phone'
                )->ignore($user->id),
            ],

            'email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' =>
                $data['email'] ?? null,
        ]);

        $user
            ->refresh()
            ->load(
                'roles:id,name,code'
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Cập nhật thông tin thành công.',
            'data' => [
                'user' => $user,
            ],
        ]);
    }
}
