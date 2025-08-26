<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Slides\Saml2\Models\Tenant;

class SamlCustomController extends Controller
{
    public function start(Request $request)
    {
        $tenant = $request->query('tenant');
        if (!$tenant) return response()->json(['error' => 'Tenant is required'], 400);

        $tenantDb = Tenant::where('key', $tenant)->first();
        if (!$tenantDb) return response()->json(['error' => 'Invalid tenant'], 400);

        $appSamlLogin = URL::to('/api/saml/' . $tenantDb->uuid . '/login');

        $chooser = 'https://accounts.google.com/AccountChooser?continue=' . urlencode($appSamlLogin);
        Log::debug('[SAML] Redirecting to chooser: ' . $chooser);
        return redirect()->away($chooser);
    }
}
