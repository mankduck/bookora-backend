<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\StoreStaffRequest;
use App\Http\Requests\Admin\Staff\UpdateStaffRequest;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StaffProfile::query()
            ->with([
                'user:id,name,email,phone,avatar,status',
                'services:id,name,slug,status',
                'schedules',
            ]);

        if ($request->filled('search')) {
            $search = $request
                ->string('search')
                ->toString();

            $query->where(function ($query) use ($search) {
                $query
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('service_id')) {
            $serviceId = $request->integer('service_id');

            $query->whereHas(
                'services',
                fn ($serviceQuery) =>
                    $serviceQuery->where(
                        'services.id',
                        $serviceId
                    )
            );
        }

        $staff = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(
                min(
                    (int) $request->input('per_page', 15),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    public function store(
        StoreStaffRequest $request
    ): JsonResponse {
        $staff = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'avatar' => $data['avatar'] ?? null,
                'status' => $data['status'],
            ]);

            $staffRole = Role::where(
                'code',
                'staff'
            )->firstOrFail();

            $user->roles()->syncWithoutDetaching([
                $staffRole->id,
            ]);

            $staff = StaffProfile::create([
                'user_id' => $user->id,
                'employee_code' => $data['employee_code'] ?? null,
                'position' => $data['position'] ?? null,
                'bio' => $data['bio'] ?? null,
                'experience_years' => $data['experience_years'] ?? 0,
                'is_bookable' => $data['is_bookable'] ?? true,
                'status' => $data['status'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncServices(
                $staff,
                $data['service_ids'] ?? []
            );

            $this->syncSchedules(
                $staff,
                $data['schedules'] ?? []
            );

            return $staff;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhân viên thành công.',
            'data' => [
                'staff' => $staff->load([
                    'user.roles',
                    'services',
                    'schedules',
                ]),
            ],
        ], 201);
    }

    public function show(
        StaffProfile $staff
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staff->load([
                    'user.roles',
                    'services',
                    'schedules',
                ]),
            ],
        ]);
    }

    public function update(
        UpdateStaffRequest $request,
        StaffProfile $staff
    ): JsonResponse {
        DB::transaction(function () use ($request, $staff) {
            $data = $request->validated();

            $userData = [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'avatar' => $data['avatar'] ?? null,
                'status' => $data['status'],
            ];

            if (!empty($data['password'])) {
                $userData['password'] =
                    Hash::make($data['password']);
            }

            $staff->user->update($userData);

            $staff->update([
                'employee_code' => $data['employee_code'] ?? null,
                'position' => $data['position'] ?? null,
                'bio' => $data['bio'] ?? null,
                'experience_years' => $data['experience_years'] ?? 0,
                'is_bookable' => $data['is_bookable'] ?? true,
                'status' => $data['status'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncServices(
                $staff,
                $data['service_ids'] ?? []
            );

            $this->syncSchedules(
                $staff,
                $data['schedules'] ?? []
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhân viên thành công.',
            'data' => [
                'staff' => $staff
                    ->fresh()
                    ->load([
                        'user.roles',
                        'services',
                        'schedules',
                    ]),
            ],
        ]);
    }

    public function destroy(
        StaffProfile $staff
    ): JsonResponse {
        if ($staff->bookingAssignments()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Nhân viên đã có lịch booking nên không thể xóa.',
            ], 422);
        }

        DB::transaction(function () use ($staff) {
            $user = $staff->user;

            $staff->services()->detach();
            $staff->schedules()->delete();
            $staff->timeOffs()->delete();

            $staff->delete();

            if ($user) {
                $user->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Xóa nhân viên thành công.',
        ]);
    }

    private function syncServices(
        StaffProfile $staff,
        array $serviceIds
    ): void {
        $syncData = [];

        foreach ($serviceIds as $serviceId) {
            $syncData[$serviceId] = [
                'status' => 'active',
            ];
        }

        $staff->services()->sync($syncData);
    }

    private function syncSchedules(
        StaffProfile $staff,
        array $schedules
    ): void {
        $staff->schedules()->delete();

        foreach ($schedules as $schedule) {
            $staff->schedules()->create([
                'day_of_week' =>
                    $schedule['day_of_week'],

                'start_time' =>
                    $schedule['start_time'],

                'end_time' =>
                    $schedule['end_time'],

                'is_working' =>
                    (bool) ($schedule['is_working'] ?? false),
            ]);
        }
    }
}
