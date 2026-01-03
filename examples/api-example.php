<?php

/**
 * API with Tenant Tokens Example
 * 
 * This example demonstrates:
 * - API token generation and management
 * - Token-based authentication
 * - Ability-based authorization
 * - Token lifecycle management
 */

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

class ApiTokenController extends Controller
{
    /**
     * Generate a new API token for the authenticated user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string|in:read,write,delete',
            'expires_in_days' => 'integer|min:1|max:365',
        ]);

        $user = TenantAuth::user();
        $tenant = TenantAuth::tenantOrFail();

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $token = $user->createTenantToken(
            $tenant,
            $validated['name'],
            $abilities,
            $expiresAt
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'expires_at' => $token->expires_at,
        ], 201);
    }

    /**
     * List all tokens for the authenticated user in current tenant.
     */
    public function index(Request $request)
    {
        $user = TenantAuth::user();
        $tenant = TenantAuth::tenantOrFail();

        $tokens = $user->tokensForTenant($tenant)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    /**
     * Revoke a specific token.
     */
    public function destroy(Request $request, int $tokenId)
    {
        $user = TenantAuth::user();
        $tenant = TenantAuth::tenantOrFail();

        $token = $user->tokensForTenant($tenant)->find($tokenId);

        if (!$token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        $user->revokeToken($token);

        return response()->json(['message' => 'Token revoked successfully']);
    }

    /**
     * Revoke all tokens for current tenant.
     */
    public function destroyAll(Request $request)
    {
        $user = TenantAuth::user();
        $tenant = TenantAuth::tenantOrFail();

        $user->revokeTenantTokens($tenant);

        return response()->json(['message' => 'All tokens revoked successfully']);
    }
}

class ApiAuthController extends Controller
{
    /**
     * Authenticate and generate API token.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        $tenant = TenantAuth::tenant();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not identified',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check tenant access
        if (!$user->hasAccessToTenant($tenant)) {
            return response()->json([
                'message' => 'No access to this tenant',
            ], 403);
        }

        // Generate token
        $token = $user->createTenantToken(
            $tenant,
            $validated['device_name'],
            ['*']
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ]);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request)
    {
        $user = TenantAuth::user();
        $tenant = TenantAuth::tenantOrFail();
        $token = $user->currentAccessToken();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleInTenant($tenant),
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'token' => [
                'name' => $token->name,
                'abilities' => $token->abilities,
                'expires_at' => $token->expires_at,
            ],
        ]);
    }
}

class ApiResourceController extends Controller
{
    /**
     * Example: List resources with ability check.
     */
    public function index(Request $request)
    {
        $user = TenantAuth::user();
        $token = $user->currentAccessToken();

        // Check if token has read ability
        if ($token->cant('read')) {
            return response()->json([
                'message' => 'Insufficient permissions',
            ], 403);
        }

        // Get tenant-scoped resources
        $resources = Resource::paginate(15);

        return response()->json($resources);
    }

    /**
     * Example: Create resource with ability check.
     */
    public function store(Request $request)
    {
        $user = TenantAuth::user();
        $token = $user->currentAccessToken();

        // Check if token has write ability
        if ($token->cant('write')) {
            return response()->json([
                'message' => 'Insufficient permissions',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $resource = Resource::create($validated);

        return response()->json($resource, 201);
    }

    /**
     * Example: Delete resource with ability check.
     */
    public function destroy(Request $request, int $id)
    {
        $user = TenantAuth::user();
        $token = $user->currentAccessToken();

        // Check if token has delete ability
        if ($token->cant('delete')) {
            return response()->json([
                'message' => 'Insufficient permissions',
            ], 403);
        }

        $resource = Resource::findOrFail($id);
        $resource->delete();

        return response()->json(['message' => 'Resource deleted']);
    }
}

// Routes (routes/api.php)
/*
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\ApiResourceController;

// Public API routes
Route::middleware(['tenant.identify'])->group(function () {
    Route::post('/login', [ApiAuthController::class, 'login']);
});

// Protected API routes with token authentication
Route::middleware(['tenant.identify', 'tenant.token'])->group(function () {
    Route::get('/me', [ApiAuthController::class, 'me']);
    
    // Token management
    Route::get('/tokens', [ApiTokenController::class, 'index']);
    Route::post('/tokens', [ApiTokenController::class, 'store']);
    Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy']);
    Route::delete('/tokens', [ApiTokenController::class, 'destroyAll']);
    
    // Resources with read ability required
    Route::middleware('tenant.token:read')->group(function () {
        Route::get('/resources', [ApiResourceController::class, 'index']);
        Route::get('/resources/{resource}', [ApiResourceController::class, 'show']);
    });
    
    // Resources with write ability required
    Route::middleware('tenant.token:write')->group(function () {
        Route::post('/resources', [ApiResourceController::class, 'store']);
        Route::put('/resources/{resource}', [ApiResourceController::class, 'update']);
    });
    
    // Resources with delete ability required
    Route::middleware('tenant.token:delete')->group(function () {
        Route::delete('/resources/{resource}', [ApiResourceController::class, 'destroy']);
    });
});
*/

// Example API Usage with cURL
/*
# 1. Login and get token
curl -X POST https://acme.myapp.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password",
    "device_name": "mobile-app"
  }'

# Response: {"token": "1|abc123...", "user": {...}, "tenant": {...}}

# 2. Use token for authenticated requests
curl https://acme.myapp.com/api/resources \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"

# 3. Create a new token with specific abilities
curl -X POST https://acme.myapp.com/api/tokens \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "read-only-token",
    "abilities": ["read"],
    "expires_in_days": 30
  }'

# 4. List all tokens
curl https://acme.myapp.com/api/tokens \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"

# 5. Revoke a token
curl -X DELETE https://acme.myapp.com/api/tokens/2 \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Accept: application/json"
*/
