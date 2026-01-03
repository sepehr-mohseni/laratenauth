<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * @property int|string $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $domain
 * @property string|null $subdomain
 * @property bool $is_active
 * @property array|null $settings
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Tenant extends Model
{
    use HasFactory;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('laratenauth.database.tenants_table', 'tenants');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'is_active',
        'settings',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the users associated with the tenant.
     *
     * @return MorphToMany<Model>
     */
    public function users(): MorphToMany
    {
        $userModel = config('laratenauth.user_model', 'App\\Models\\User');
        $pivotTable = config('laratenauth.database.tenant_user_table', 'tenant_user');

        return $this->morphedByMany($userModel, 'tenant_userable', $pivotTable)
            ->withPivot(['role', 'permissions', 'is_default'])
            ->withTimestamps();
    }

    /**
     * Get the tokens associated with the tenant.
     *
     * @return HasMany<TenantToken>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(TenantToken::class, 'tenant_id');
    }

    /**
     * Check if the tenant is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Activate the tenant.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the tenant.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        return data_get($settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);

        return $this->update(['settings' => $settings]);
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $metadata = $this->metadata ?? [];

        return data_get($metadata, $key, $default);
    }

    /**
     * Set a metadata value.
     */
    public function setMeta(string $key, mixed $value): bool
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);

        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Add a user to the tenant.
     */
    public function addUser(Model $user, ?string $role = null, array $permissions = [], bool $isDefault = false): void
    {
        $this->users()->attach($user->getKey(), [
            'role' => $role,
            'permissions' => json_encode($permissions),
            'is_default' => $isDefault,
        ]);
    }

    /**
     * Remove a user from the tenant.
     */
    public function removeUser(Model $user): void
    {
        $this->users()->detach($user->getKey());
    }

    /**
     * Check if a user belongs to this tenant.
     */
    public function hasUser(Model $user): bool
    {
        $userTable = $user->getTable();
        return $this->users()->where("{$userTable}.id", $user->getKey())->exists();
    }

    /**
     * Get the user's role in this tenant.
     */
    public function getUserRole(Model $user): ?string
    {
        $userTable = $user->getTable();
        $pivotRecord = $this->users()->where("{$userTable}.id", $user->getKey())->first();

        return $pivotRecord?->pivot?->role;
    }

    /**
     * Get the user's permissions in this tenant.
     */
    public function getUserPermissions(Model $user): array
    {
        $userTable = $user->getTable();
        $pivotRecord = $this->users()->where("{$userTable}.id", $user->getKey())->first();
        $permissions = $pivotRecord?->pivot?->permissions;

        if (is_string($permissions)) {
            return json_decode($permissions, true) ?? [];
        }

        return $permissions ?? [];
    }

    /**
     * Generate a unique slug for the tenant.
     */
    public static function generateSlug(string $name): string
    {
        $slug = str($name)->slug()->toString();
        $count = static::where('slug', 'like', $slug.'%')->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Scope a query to only include active tenants.
     */
    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive tenants.
     */
    public function scopeInactive($query): mixed
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to find by domain.
     */
    public function scopeByDomain($query, string $domain): mixed
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope a query to find by subdomain.
     */
    public function scopeBySubdomain($query, string $subdomain): mixed
    {
        return $query->where('subdomain', $subdomain);
    }

    /**
     * Scope a query to find by slug.
     */
    public function scopeBySlug($query, string $slug): mixed
    {
        return $query->where('slug', $slug);
    }
}
