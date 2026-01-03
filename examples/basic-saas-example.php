<?php

/**
 * Basic SaaS Application Example
 * 
 * This example demonstrates a typical SaaS application setup with:
 * - Tenant registration
 * - User authentication per tenant
 * - Multi-tenant user management
 * - Tenant switching
 */

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;

class TenantRegistrationController extends Controller
{
    /**
     * Register a new tenant with the first user (owner).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'subdomain' => 'required|string|unique:tenants,subdomain',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create the tenant
        $tenant = Tenant::create([
            'name' => $validated['company_name'],
            'slug' => $validated['subdomain'],
            'subdomain' => $validated['subdomain'],
            'auth_driver' => 'session',
            'is_active' => true,
        ]);

        // Create the user (owner)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Attach user to tenant as owner
        $user->attachToTenant($tenant, 'owner', ['*']);

        // Log the user in
        Auth::login($user);

        // Set tenant context
        set_tenant($tenant);

        return redirect()->route('dashboard')->with('success', 'Welcome to your new workspace!');
    }
}

class TenantAuthController extends Controller
{
    /**
     * Handle tenant-scoped login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Attempt authentication
        if (Auth::guard('web')->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            // Verify user has access to current tenant
            $tenant = TenantAuth::tenant();
            $user = Auth::user();

            if (!$user->hasAccessToTenant($tenant)) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'You do not have access to this workspace.',
                ]);
            }

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

class TenantUserController extends Controller
{
    /**
     * Invite a user to the current tenant.
     */
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member,guest',
        ]);

        $tenant = TenantAuth::tenantOrFail();

        // Create or find user
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => explode('@', $validated['email'])[0],
                'password' => Hash::make(str()->random(32)), // Temporary password
            ]
        );

        // Attach to tenant
        $user->attachToTenant($tenant, $validated['role'], [], [
            'invited_by' => auth()->id(),
            'invited_at' => now(),
        ]);

        // Send invitation email (implement your email logic)
        // Mail::to($user)->send(new TenantInvitation($tenant, $user));

        return back()->with('success', 'User invited successfully!');
    }

    /**
     * Remove a user from the tenant.
     */
    public function remove(Request $request, User $user)
    {
        $tenant = TenantAuth::tenantOrFail();

        // Prevent removing yourself
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot remove yourself.']);
        }

        // Prevent removing if user is owner
        if ($user->getRoleInTenant($tenant) === 'owner') {
            return back()->withErrors(['error' => 'Cannot remove tenant owner.']);
        }

        $user->detachFromTenant($tenant);

        return back()->with('success', 'User removed successfully!');
    }

    /**
     * Update user role in tenant.
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,member,guest',
        ]);

        $tenant = TenantAuth::tenantOrFail();

        $user->updateRoleInTenant($tenant, $validated['role']);

        return back()->with('success', 'User role updated successfully!');
    }
}

class TenantSwitchController extends Controller
{
    /**
     * Show available tenants for switching.
     */
    public function index()
    {
        $user = auth()->user();
        $currentTenant = TenantAuth::tenant();
        $tenants = $user->activeTenants;

        return view('tenant-switch', compact('tenants', 'currentTenant'));
    }

    /**
     * Switch to a different tenant.
     */
    public function switch(Request $request, Tenant $tenant)
    {
        $user = auth()->user();

        // Verify access
        if (!$user->hasAccessToTenant($tenant)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Switch tenant
        TenantAuth::switchTenant($tenant);

        return redirect()
            ->route('dashboard')
            ->with('success', "Switched to {$tenant->name}");
    }
}

class DashboardController extends Controller
{
    /**
     * Show the tenant dashboard.
     */
    public function index()
    {
        $tenant = TenantAuth::tenantOrFail();
        $user = TenantAuth::user();

        // Get tenant-specific data
        $stats = [
            'users' => $tenant->users()->count(),
            'role' => $user->getRoleInTenant($tenant),
            'tenants_count' => $user->tenants()->count(),
        ];

        return view('dashboard', compact('tenant', 'user', 'stats'));
    }
}

// Routes (routes/web.php)
/*
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\DashboardController;

// Public routes
Route::get('/register', [TenantRegistrationController::class, 'create'])->name('register');
Route::post('/register', [TenantRegistrationController::class, 'register']);

// Tenant-scoped routes
Route::middleware(['tenant.identify'])->group(function () {
    Route::get('/login', [TenantAuthController::class, 'create'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'login']);
});

// Protected tenant routes
Route::middleware(['tenant.identify', 'tenant.auth', 'tenant.access'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [TenantAuthController::class, 'logout'])->name('logout');
    
    // Tenant switching
    Route::get('/switch', [TenantSwitchController::class, 'index'])->name('tenant.switch');
    Route::post('/switch/{tenant}', [TenantSwitchController::class, 'switch'])->name('tenant.switch.do');
    
    // User management (admin only)
    Route::middleware('can:manage-users')->group(function () {
        Route::post('/users/invite', [TenantUserController::class, 'invite'])->name('users.invite');
        Route::delete('/users/{user}', [TenantUserController::class, 'remove'])->name('users.remove');
        Route::patch('/users/{user}/role', [TenantUserController::class, 'updateRole'])->name('users.update-role');
    });
});
*/
