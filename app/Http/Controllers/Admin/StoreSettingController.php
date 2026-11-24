<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{
    private const DEFAULTS = [
        'store_name' => ['group' => 'general', 'value' => 'EcomMS Store'],
        'support_email' => ['group' => 'general', 'value' => 'support@example.com'],
        'currency' => ['group' => 'general', 'value' => 'USD'],
        'timezone' => ['group' => 'general', 'value' => 'UTC'],
        'shipping_flat_rate' => ['group' => 'shipping', 'value' => '0.00'],
        'free_shipping_threshold' => ['group' => 'shipping', 'value' => '100.00'],
        'low_stock_threshold' => ['group' => 'operations', 'value' => '10'],
        'auto_confirm_orders' => ['group' => 'operations', 'value' => 'false'],
        'payment_sandbox_mode' => ['group' => 'payments', 'value' => 'true'],
        'payment_capture_mode' => ['group' => 'payments', 'value' => 'automatic'],
    ];

    public function index(): JsonResponse
    {
        abort_unless(
            auth('staff')->user()?->can('staff.view'),
            403,
            'You do not have permission [staff.view].'
        );

        $saved = StoreSetting::query()->get()->keyBy('key');
        $settings = [];

        foreach (self::DEFAULTS as $key => $meta) {
            $settings[$key] = $saved->has($key) ? (string) $saved[$key]->value : $meta['value'];
        }

        foreach ($saved as $key => $row) {
            if (!array_key_exists($key, $settings)) {
                $settings[$key] = (string) $row->value;
            }
        }

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(
            auth('staff')->user()?->can('staff.update'),
            403,
            'You do not have permission [staff.update].'
        );

        $validated = $request->validate([
            'store_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'currency' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'shipping_flat_rate' => ['nullable', 'string', 'max:32'],
            'free_shipping_threshold' => ['nullable', 'string', 'max:32'],
            'low_stock_threshold' => ['nullable', 'string', 'max:32'],
            'auto_confirm_orders' => ['nullable', 'string', 'max:16'],
            'payment_sandbox_mode' => ['nullable', 'string', 'max:16'],
            'payment_capture_mode' => ['nullable', 'string', 'max:32'],
        ]);

        foreach ($validated as $key => $value) {
            $group = self::DEFAULTS[$key]['group'] ?? 'general';
            StoreSetting::updateOrCreate(
                ['key' => $key],
                ['group' => $group, 'value' => $value === null ? '' : (string) $value]
            );
        }

        return $this->index();
    }
}
