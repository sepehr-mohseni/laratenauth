<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int|string $id
 * @property string $tokenable_type
 * @property int|string $tokenable_id
 * @property int|string|null $tenant_id
 * @property string $name
 * @property string $token
 * @property array|null $abilities
 * @property bool $revoked
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TenantToken extends Model
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

        $this->table = config('laratenauth.database.tokens_table', 'tenant_tokens');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'tenant_id',
        'name',
        'token',
        'abilities',
        'revoked',
        'last_used_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'abilities' => 'array',
        'revoked' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Get the tokenable model.
     *
     * @return MorphTo<Model, TenantToken>
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the tenant this token belongs to.
     *
     * @return BelongsTo<Tenant, TenantToken>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the token is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked === true;
    }

    /**
     * Check if the token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }

    /**
     * Check if the token has a specific ability.
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        // Wildcard ability
        if (in_array('*', $abilities)) {
            return true;
        }

        return in_array($ability, $abilities);
    }

    /**
     * Check if the token cannot perform an ability.
     */
    public function cannot(string $ability): bool
    {
        return ! $this->can($ability);
    }

    /**
     * Revoke the token.
     */
    public function revoke(): bool
    {
        return $this->update(['revoked' => true]);
    }

    /**
     * Restore a revoked token.
     */
    public function restore(): bool
    {
        return $this->update(['revoked' => false]);
    }

    /**
     * Extend the token expiration.
     */
    public function extendExpiration(int $days): bool
    {
        $newExpiration = $this->expires_at
            ? $this->expires_at->addDays($days)
            : Carbon::now()->addDays($days);

        return $this->update(['expires_at' => $newExpiration]);
    }

    /**
     * Mark the token as used.
     */
    public function markAsUsed(): bool
    {
        return $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * Create a new token.
     */
    public static function createToken(
        Model $tokenable,
        string $name,
        array $abilities = ['*'],
        ?int $tenantId = null,
        ?Carbon $expiresAt = null
    ): array {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $token = static::create([
            'tokenable_type' => get_class($tokenable),
            'tokenable_id' => $tokenable->getKey(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'revoked' => false,
        ]);

        return [
            'token' => $token,
            'plainTextToken' => $token->id.'|'.$plainToken,
        ];
    }

    /**
     * Find a token by its plain text value.
     */
    public static function findToken(string $token): ?static
    {
        if (str_contains($token, '|')) {
            [$id, $plainToken] = explode('|', $token, 2);

            $tokenRecord = static::where('id', $id)
                ->where('revoked', false)
                ->first();

            if ($tokenRecord && hash_equals($tokenRecord->token, hash('sha256', $plainToken))) {
                return $tokenRecord;
            }

            return null;
        }

        return static::where('token', hash('sha256', $token))
            ->where('revoked', false)
            ->first();
    }

    /**
     * Scope a query to only include valid tokens.
     */
    public function scopeValid($query): mixed
    {
        return $query->where('revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * Scope a query to only include revoked tokens.
     */
    public function scopeRevoked($query): mixed
    {
        return $query->where('revoked', true);
    }

    /**
     * Scope a query to only include expired tokens.
     */
    public function scopeExpired($query): mixed
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Scope a query to only include tokens for a specific tenant.
     */
    public function scopeForTenant($query, int|string $tenantId): mixed
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include tokens with a specific ability.
     */
    public function scopeWithAbility($query, string $ability): mixed
    {
        return $query->where(function ($q) use ($ability) {
            $q->whereJsonContains('abilities', '*')
                ->orWhereJsonContains('abilities', $ability);
        });
    }
}
