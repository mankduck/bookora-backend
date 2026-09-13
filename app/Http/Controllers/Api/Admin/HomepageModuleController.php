<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageModule;
use App\Models\Service;
use App\Services\CssSanitizer;
use App\Services\HtmlSanitizer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HomepageModuleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'modules' => HomepageModule::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(),
            ],
        ]);
    }

    public function serviceOptions(): JsonResponse
    {
        $services = Service::query()
            ->where('status', 'active')
            ->with('category:id,name')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'category_id',
                'name',
                'slug',
                'thumbnail',
                'base_price',
                'is_featured',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    public function store(
        Request $request,
        HtmlSanitizer $html,
        CssSanitizer $css,
        NotificationService $notifications,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'show_in_nav' => ['boolean'],
            'nav_label' => ['nullable', 'string', 'max:100'],
        ]);

        $module = HomepageModule::create([
            'type' => 'custom_html',
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'title' => $data['title'] ?? $data['name'],
            'content' => $html->sanitize($data['content']),
            'custom_css' => $css->sanitize($data['custom_css'] ?? null),
            'sort_order' => (int) HomepageModule::max('sort_order') + 10,
            'is_enabled' => true,
            'is_locked' => false,
            'show_in_nav' => (bool) ($data['show_in_nav'] ?? true),
            'nav_label' => $data['nav_label'] ?? $data['name'],
        ]);

        $this->notifySiteChanged(
            $notifications,
            'Đã thêm một khu vực mới trên trang chủ.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm module.',
            'data' => ['module' => $module],
        ], 201);
    }

    public function update(
        Request $request,
        HomepageModule $module,
        HtmlSanitizer $html,
        CssSanitizer $css,
        NotificationService $notifications,
    ): JsonResponse {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'show_in_nav' => ['sometimes', 'boolean'],
            'nav_label' => ['nullable', 'string', 'max:100'],
            'settings' => ['nullable', 'array'],
        ];

        if ($module->type === 'services') {
            $rules['settings.service_ids'] = ['sometimes', 'array', 'max:6'];
            $rules['settings.service_ids.*'] = [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ];
        }

        $data = $request->validate($rules);

        if ($module->type === 'custom_html') {
            if (array_key_exists('content', $data)) {
                $data['content'] = $html->sanitize($data['content']);
            }

            if (array_key_exists('custom_css', $data)) {
                $data['custom_css'] = $css->sanitize($data['custom_css']);
            }
        } else {
            unset($data['content'], $data['custom_css']);
        }

        if ($module->type === 'services' && array_key_exists('settings', $data)) {
            $currentSettings = $module->settings ?? [];
            $incomingSettings = $data['settings'] ?? [];
            $serviceIds = array_values($incomingSettings['service_ids'] ?? []);

            $data['settings'] = array_merge($currentSettings, [
                'service_ids' => $serviceIds,
                'limit' => min(6, count($serviceIds)),
            ]);
        }

        $module->update($data);

        $this->notifySiteChanged(
            $notifications,
            'Bố cục hoặc nội dung trang chủ vừa được cập nhật.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật module.',
            'data' => ['module' => $module->fresh()],
        ]);
    }

    public function destroy(
        HomepageModule $module,
        NotificationService $notifications,
    ): JsonResponse {
        if ($module->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Module hệ thống không thể xóa. Bạn có thể tắt module.',
            ], 422);
        }

        $module->delete();

        $this->notifySiteChanged(
            $notifications,
            'Một khu vực trên trang chủ vừa được thay đổi.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa module.',
        ]);
    }

    public function reorder(
        Request $request,
        NotificationService $notifications,
    ): JsonResponse {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:homepage_modules,id'],
        ]);

        DB::transaction(function () use ($data) {
            collect($data['ids'])->each(
                fn ($id, $index) => HomepageModule::whereKey($id)
                    ->update(['sort_order' => ($index + 1) * 10]),
            );
        });

        $this->notifySiteChanged(
            $notifications,
            'Thứ tự hiển thị trên trang chủ vừa được cập nhật.',
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thứ tự module.',
        ]);
    }

    private function notifySiteChanged(NotificationService $notifications, string $message): void
    {
        $notifications->sendToCustomers(
            'site_updated',
            'Giao diện website vừa thay đổi',
            $message . ' Thay đổi đã được đồng bộ tự động.',
            'info',
            '/',
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'module';
        $slug = $base;
        $index = 2;

        while (HomepageModule::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $index++;
        }

        return $slug;
    }
}
