<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show(SiteSettingService $settings): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['settings' => $settings->all()]]);
    }

    public function update(Request $request, SiteSettingService $settings, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'business_hours' => ['nullable', 'string', 'max:255'],
            'map_embed_url' => ['nullable', 'url', 'max:1500'],
            'facebook_url' => ['nullable', 'url', 'max:1500'],
            'instagram_url' => ['nullable', 'url', 'max:1500'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'admin_language' => ['nullable', 'in:vi,en'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
        ]);

        if ($request->hasFile('logo')) {
            $old = $settings->all()['logo_url'] ?? null;
            if ($old && str_contains($old, '/storage/settings/')) {
                $path = preg_replace('#^.*?/storage/#', '', $old);
                if ($path) Storage::disk('public')->delete($path);
            }
            $path = $request->file('logo')->store('settings', 'public');
            $data['logo_url'] = url(Storage::url($path));
        }
        unset($data['logo']);

        $updated = $settings->update($data);

        if ($request->hasAny(['site_name', 'company_name', 'tagline', 'primary_color', 'secondary_color']) || $request->hasFile('logo')) {
            $notifications->sendToCustomers(
                'site_updated',
                'Giao diện website vừa thay đổi',
                'Website vừa được cập nhật nhận diện hoặc giao diện. Thay đổi đã được đồng bộ tự động.',
                'info',
                '/'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật cài đặt website.',
            'data' => ['settings' => $updated],
        ]);
    }
}
