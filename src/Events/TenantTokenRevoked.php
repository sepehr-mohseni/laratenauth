<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Sepehr_Mohseni\LaraTenAuth\Models\TenantToken;

class TenantTokenRevoked
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public TenantToken $token
    ) {
    }
}
