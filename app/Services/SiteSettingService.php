<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteSettingService
{
    public function defaults(): array
    {
        return [
            'site_name' => 'Bookora',
            'company_name' => 'Bookora',
            'tagline' => 'Đặt lịch dịch vụ đơn giản hơn.',
            'logo_url' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'business_hours' => '08:00 - 21:00',
            'map_embed_url' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'primary_color' => '#181916',
            'secondary_color' => '#f4f4f1',
            'admin_language' => 'vi',
        ];
    }

    public function all(): array
    {
        $values = SiteSetting::query()->pluck('value', 'key')->all();
        return array_merge($this->defaults(), $values);
    }

    public function update(array $values): array
    {
        $groups = [
            'site_name' => 'general', 'company_name' => 'general', 'tagline' => 'general', 'logo_url' => 'general',
            'phone' => 'contact', 'email' => 'contact', 'address' => 'contact', 'business_hours' => 'contact', 'map_embed_url' => 'contact',
            'facebook_url' => 'social', 'instagram_url' => 'social',
            'primary_color' => 'theme', 'secondary_color' => 'theme', 'admin_language' => 'locale',
        ];

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $groups)) {
                continue;
            }

            SiteSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $groups[$key],
                    'value' => $value,
                    'type' => str_contains($key, 'color') ? 'color' : 'text',
                ]
            );
        }

        return $this->all();
    }
}
