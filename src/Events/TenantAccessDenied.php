<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantAccessDenied
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ?Authenticatable $user,
        public ?Model $tenant
    ) {
    }
}
