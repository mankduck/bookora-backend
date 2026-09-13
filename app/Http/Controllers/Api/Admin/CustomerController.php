<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customer\StoreCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerRequest;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function __construct(
        private readonly AccountStatusService $accountStatusService
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        $search = trim(
            $request
                ->string('search')
                ->toString()
        );

        $status = trim(
            $request
                ->string('status')
                ->toString()
        );

        $perPage =
            max(
                5,
                min(
                    (int) $request->input(
                        'per_page',
                        15
                    ),
                    100
                )
            );

        $query =
            User::query()
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.avatar',
                    'users.status',
                    'users.email_verified_at',
                    'users.last_login_at',
                    'users.created_at',
                    'users.updated_at',
                ])
                ->whereHas(
                    'roles',
                    function ($query) {
                        $query->where(
                            'roles.code',
                            'customer'
                        );
                    }
                );

        if ($search !== '') {
            $query->where(
                function ($query) use (
                    $search
                ) {
                    $query
                        ->where(
                            'users.name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'users.email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'users.phone',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            in_array(
                $status,
                [
                    'active',
                    'inactive',
                ],
                true
            )
        ) {
            $query->where(
                'users.status',
                $status
            );
        }

        $customers =
            $query
                ->orderByDesc(
                    'users.created_at'
                )
                ->paginate(
                    $perPage
                );

        $customerIds =
            collect(
                $customers->items()
            )
                ->pluck('id')
                ->values();

        $bookingStats =
            collect();

        if (
            $customerIds
                ->isNotEmpty()
        ) {
            $bookingStats =
                Booking::query()
                    ->select([
                        'customer_id',

                        DB::raw(
                            'COUNT(*) AS bookings_count'
                        ),

                        DB::raw(
                            "SUM(
                                CASE
                                    WHEN status = 'completed'
                                    THEN 1
                                    ELSE 0
                                END
                            ) AS completed_bookings_count"
                        ),

                        DB::raw(
                            "COALESCE(
                                SUM(
                                    CASE
                                        WHEN status = 'completed'
                                        AND payment_status = 'paid'
                                        THEN total_amount
                                        ELSE 0
                                    END
                                ),
                                0
                            ) AS total_spent"
                        ),

                        DB::raw(
                            'MAX(start_at) AS latest_booking_at'
                        ),
                    ])
                    ->whereIn(
                        'customer_id',
                        $customerIds
                    )
                    ->groupBy(
                        'customer_id'
                    )
                    ->get()
                    ->keyBy(
                        'customer_id'
                    );
        }

        $customers
            ->getCollection()
            ->transform(
                function ($customer) use (
                    $bookingStats
                ) {
                    $stats =
                        $bookingStats
                            ->get(
                                $customer->id
                            );

                    return [
                        'id' =>
                            $customer->id,

                        'name' =>
                            $customer->name,

                        'email' =>
                            $customer->email,

                        'phone' =>
                            $customer->phone,

                        'avatar' =>
                            $customer->avatar,

                        'status' =>
                            $customer->status,

                        'email_verified_at' =>
                            $customer
                                ->email_verified_at,

                        'last_login_at' =>
                            $customer
                                ->last_login_at,

                        'created_at' =>
                            $customer
                                ->created_at,

                        'updated_at' =>
                            $customer
                                ->updated_at,

                        'bookings_count' =>
                            (int) (
                                $stats
                                    ?->bookings_count
                                ?? 0
                            ),

                        'completed_bookings_count' =>
                            (int) (
                                $stats
                                    ?->completed_bookings_count
                                ?? 0
                            ),

                        'total_spent' =>
                            (float) (
                                $stats
                                    ?->total_spent
                                ?? 0
                            ),

                        'latest_booking_at' =>
                            $stats
                                ?->latest_booking_at,
                    ];
                }
            );

        return response()->json([
            'data' => [
                'customers' =>
                    $customers->items(),

                'pagination' => [
                    'current_page' =>
                        $customers
                            ->currentPage(),

                    'last_page' =>
                        $customers
                            ->lastPage(),

                    'per_page' =>
                        $customers
                            ->perPage(),

                    'total' =>
                        $customers
                            ->total(),

                    'from' =>
                        $customers
                            ->firstItem(),

                    'to' =>
                        $customers
                            ->lastItem(),
                ],
            ],
        ]);
    }

    public function store(
        StoreCustomerRequest $request
    ): JsonResponse {
        $data =
            $request->validated();

        $customer =
            DB::transaction(
                function () use (
                    $data
                ) {
                    $customer =
                        User::create([
                            'name' =>
                                $data['name'],

                            'email' =>
                                $data['email']
                                ?? null,

                            'phone' =>
                                $data['phone'],

                            'password' =>
                                Hash::make(
                                    $data[
                                        'password'
                                    ]
                                ),

                            'status' =>
                                $data[
                                    'status'
                                ],
                        ]);

                    $customerRole =
                        Role::query()
                            ->where(
                                'code',
                                'customer'
                            )
                            ->firstOrFail();

                    $customer
                        ->roles()
                        ->syncWithoutDetaching([
                            $customerRole
                                ->id,
                        ]);

                    return $customer;
                }
            );

        return response()->json([
            'message' =>
                'Tạo khách hàng thành công.',

            'data' => [
                'customer' => [
                    'id' =>
                        $customer->id,

                    'name' =>
                        $customer->name,

                    'email' =>
                        $customer->email,

                    'phone' =>
                        $customer->phone,

                    'avatar' =>
                        $customer->avatar,

                    'status' =>
                        $customer->status,

                    'email_verified_at' =>
                        $customer
                            ->email_verified_at,

                    'last_login_at' =>
                        $customer
                            ->last_login_at,

                    'created_at' =>
                        $customer
                            ->created_at,

                    'updated_at' =>
                        $customer
                            ->updated_at,

                    'bookings_count' => 0,

                    'completed_bookings_count' => 0,

                    'total_spent' => 0,

                    'latest_booking_at' => null,
                ],
            ],
        ], 201);
    }

    public function show(
        User $user
    ): JsonResponse {
        $this->ensureCustomer(
            $user
        );

        $baseBookingQuery =
            Booking::query()
                ->where(
                    'customer_id',
                    $user->id
                );

        $bookingsCount =
            (clone $baseBookingQuery)
                ->count();

        $completedBookingsCount =
            (clone $baseBookingQuery)
                ->where(
                    'status',
                    'completed'
                )
                ->count();

        $totalSpent =
            (clone $baseBookingQuery)
                ->where(
                    'status',
                    'completed'
                )
                ->where(
                    'payment_status',
                    'paid'
                )
                ->sum(
                    'total_amount'
                );

        $bookings =
            (clone $baseBookingQuery)
                ->with([
                    'items',
                ])
                ->orderByDesc(
                    'start_at'
                )
                ->get();

        return response()->json([
            'data' => [
                'customer' => [
                    'id' =>
                        $user->id,

                    'name' =>
                        $user->name,

                    'email' =>
                        $user->email,

                    'phone' =>
                        $user->phone,

                    'avatar' =>
                        $user->avatar,

                    'status' =>
                        $user->status,

                    'email_verified_at' =>
                        $user
                            ->email_verified_at,

                    'last_login_at' =>
                        $user
                            ->last_login_at,

                    'created_at' =>
                        $user->created_at,

                    'updated_at' =>
                        $user->updated_at,

                    'stats' => [
                        'bookings_count' =>
                            $bookingsCount,

                        'completed_bookings_count' =>
                            $completedBookingsCount,

                        'total_spent' =>
                            (float)
                            $totalSpent,

                        'latest_booking_at' =>
                            $bookings
                                ->first()
                                ?->start_at,
                    ],

                    'bookings' =>
                        $bookings,
                ],
            ],
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        User $user
    ): JsonResponse {
        $this->ensureCustomer(
            $user
        );

        $data =
            $request
                ->validated();

        /**
         * Chỉ kiểm tra khi đang active
         * và admin muốn chuyển sang inactive.
         */
        if (
            $user->status ===
                'active'
            &&
            $data['status'] ===
                'inactive'
        ) {
            $this
                ->accountStatusService
                ->validateCustomerCanDeactivate(
                    $user->id
                );
        }

        $user->fill([
            'name' =>
                $data['name'],

            'email' =>
                $data['email']
                ?? null,

            'phone' =>
                $data['phone'],

            'status' =>
                $data['status'],
        ]);

        $user->save();

        return response()->json([
            'message' =>
                'Cập nhật khách hàng thành công.',

            'data' => [
                'customer' => [
                    'id' =>
                        $user->id,

                    'name' =>
                        $user->name,

                    'email' =>
                        $user->email,

                    'phone' =>
                        $user->phone,

                    'avatar' =>
                        $user->avatar,

                    'status' =>
                        $user->status,

                    'email_verified_at' =>
                        $user
                            ->email_verified_at,

                    'last_login_at' =>
                        $user
                            ->last_login_at,

                    'created_at' =>
                        $user
                            ->created_at,

                    'updated_at' =>
                        $user
                            ->updated_at,
                ],
            ],
        ]);
    }

    private function ensureCustomer(
        User $user
    ): void {
        $isCustomer =
            $user
                ->roles()
                ->where(
                    'roles.code',
                    'customer'
                )
                ->exists();

        abort_unless(
            $isCustomer,
            404,
            'Không tìm thấy khách hàng.'
        );
    }
}