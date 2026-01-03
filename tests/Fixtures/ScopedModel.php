<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Sepehr_Mohseni\LaraTenAuth\Traits\TenantScoped;

class ScopedModel extends Model
{
    use TenantScoped;

    protected $table = 'scoped_models';

    protected $fillable = [
        'tenant_id',
        'name',
    ];
}
