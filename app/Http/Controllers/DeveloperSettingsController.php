<?php

namespace App\Http\Controllers;

use App\Services\DeveloperSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperSettingsController extends Controller
{
    public function __construct(protected DeveloperSettingsService $settings)
    {
    }

    /**
     * Return all current developer settings.
     *
     * GET /developer/settings
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settings->all(),
            'defaults' => $this->settings->defaults(),
            'diff'     => $this->settings->diff(),
        ]);
    }

    /**
     * Update one or more developer settings for the current request lifecycle.
     *
     * Note: settings are applied to the running config only and are NOT persisted
     * to .env. Changes will be lost after the current request ends. To make
     * permanent changes, update the corresponding DEV_* variables in .env.
     *
     * PATCH /developer/settings
     */
    public function update(Request $request): JsonResponse
    {
        $result = $this->settings->validate($request->all());

        if (!$result['valid']) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $result['errors'],
            ], 422);
        }

        $this->settings->apply($result['data']);

        return response()->json([
            'message'  => 'Developer settings updated.',
            'settings' => $this->settings->all(),
        ]);
    }

    /**
     * Reset all developer settings to their default values for the current request lifecycle.
     *
     * Note: this resets the running config only and does NOT update .env.
     *
     * POST /developer/settings/reset
     */
    public function reset(): JsonResponse
    {
        $this->settings->apply($this->settings->defaults());

        return response()->json([
            'message'  => 'Developer settings reset to defaults.',
            'settings' => $this->settings->all(),
        ]);
    }
}
