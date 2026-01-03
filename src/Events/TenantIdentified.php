<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantIdentified
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Model $tenant
    ) {
    }
}
