<?php

use App\Http\Controllers\Api\Admin\AdminBookingCreateController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Api\Admin\ServiceCategoryController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\ServiceVariantController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;

use App\Http\Controllers\Api\Auth\AuthController;

use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\Customer\PaymentProofController as CustomerPaymentProofController;
use App\Http\Controllers\Api\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\PaymentProofImageController;
use App\Http\Controllers\Api\NotificationController;

use App\Http\Controllers\Api\Public\AvailabilityController;
use App\Http\Controllers\Api\Public\BookingController as PublicBookingController;
use App\Http\Controllers\Api\Public\CouponController;
use App\Http\Controllers\Api\Public\ServiceCatalogController;

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('public')
        ->group(function () {

            Route::get(
                '/service-categories',
                [
                    ServiceCatalogController::class,
                    'categories',
                ]
            );

            Route::get(
                '/services',
                [
                    ServiceCatalogController::class,
                    'services',
                ]
            );

            Route::get(
                '/services/{slug}',
                [
                    ServiceCatalogController::class,
                    'show',
                ]
            );

            Route::post(
                '/coupons/check',
                [
                    CouponController::class,
                    'check',
                ]
            );
        });

    Route::get(
        '/availability',
        AvailabilityController::class
    );

    Route::prefix('auth')
        ->group(function () {

            Route::post(
                '/register',
                [
                    AuthController::class,
                    'register',
                ]
            );

            Route::post(
                '/login',
                [
                    AuthController::class,
                    'login',
                ]
            );

            Route::middleware(
                'auth:sanctum'
            )
                ->group(function () {

                    Route::get(
                        '/me',
                        [
                            AuthController::class,
                            'me',
                        ]
                    );

                    Route::post(
                        '/logout',
                        [
                            AuthController::class,
                            'logout',
                        ]
                    );
                });
        });

    Route::middleware('auth:sanctum')
    ->get(
        '/payment-proofs/{proof}/image',
        PaymentProofImageController::class
    );

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::middleware([
        'auth:sanctum',
        'role:admin',
    ])
        ->prefix('admin')
        ->group(function () {

            Route::apiResource(
                'service-categories',
                ServiceCategoryController::class
            );

            Route::apiResource(
                'services',
                ServiceController::class
            );

            Route::post(
                '/services/{service}/variants',
                [
                    ServiceVariantController::class,
                    'store',
                ]
            );

            Route::put(
                '/services/{service}/variants/{variant}',
                [
                    ServiceVariantController::class,
                    'update',
                ]
            );

            Route::delete(
                '/services/{service}/variants/{variant}',
                [
                    ServiceVariantController::class,
                    'destroy',
                ]
            );

            Route::apiResource(
                'staff',
                StaffController::class
            );

            Route::apiResource(
                'coupons',
                AdminCouponController::class
            );

            Route::get(
                '/customers',
                [
                    AdminCustomerController::class,
                    'index',
                ]
            );

            Route::post(
                '/customers',
                [
                    AdminCustomerController::class,
                    'store',
                ]
            );

            Route::get(
                '/customers/{user}',
                [
                    AdminCustomerController::class,
                    'show',
                ]
            );

            Route::put(
                '/customers/{user}',
                [
                    AdminCustomerController::class,
                    'update',
                ]
            );

            Route::get(
                '/bookings',
                [
                    AdminBookingController::class,
                    'index',
                ]
            );

            Route::post(
                '/bookings',
                [
                    AdminBookingCreateController::class,
                    'store',
                ]
            );

            Route::get(
                '/bookings/{booking}',
                [
                    AdminBookingController::class,
                    'show',
                ]
            );

            Route::post(
                '/bookings/{booking}/confirm',
                [
                    AdminBookingController::class,
                    'confirm',
                ]
            );

            Route::put(
                '/bookings/{booking}/status',
                [
                    AdminBookingController::class,
                    'updateStatus',
                ]
            );

            Route::get(
                '/bookings/{booking}/eligible-staff',
                [
                    AdminBookingController::class,
                    'eligibleStaff',
                ]
            );

            Route::post(
                '/bookings/{booking}/assign-primary',
                [
                    AdminBookingController::class,
                    'assignPrimary',
                ]
            );

            Route::post(
                '/bookings/{booking}/mark-deposit-paid',
                [
                    AdminPaymentProofController::class,
                    'markDepositPaid',
                ]
            );

            Route::post(
                '/bookings/{booking}/mark-paid',
                [
                    AdminPaymentProofController::class,
                    'markPaid',
                ]
            );

            Route::post(
                '/payment-proofs/{proof}/approve',
                [
                    AdminPaymentProofController::class,
                    'approve',
                ]
            );

            Route::post(
                '/payment-proofs/{proof}/reject',
                [
                    AdminPaymentProofController::class,
                    'reject',
                ]
            );
        });

    Route::middleware([
        'auth:sanctum',
        'role:staff',
    ])
        ->prefix('staff')
        ->group(function () {
            //
        });

    Route::middleware([
        'auth:sanctum',
        'role:customer',
    ])
        ->prefix('customer')
        ->group(function () {

            Route::get(
                '/profile',
                [
                    CustomerProfileController::class,
                    'show',
                ]
            );

            Route::put(
                '/profile',
                [
                    CustomerProfileController::class,
                    'update',
                ]
            );

            Route::post(
                '/bookings',
                [
                    PublicBookingController::class,
                    'store',
                ]
            );

            Route::get(
                '/bookings',
                [
                    CustomerBookingController::class,
                    'index',
                ]
            );

            Route::get(
                '/bookings/{booking}',
                [
                    CustomerBookingController::class,
                    'show',
                ]
            );

            Route::post(
                '/bookings/{booking}/payment-proof',
                [
                    CustomerPaymentProofController::class,
                    'store',
                ]
            );

            Route::delete(
                '/bookings/{booking}/payment-proof/{proof}',
                [
                    CustomerPaymentProofController::class,
                    'destroy',
                ]
            );
        });
});