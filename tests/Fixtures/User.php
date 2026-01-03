<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenants;
use Sepehr_Mohseni\LaraTenAuth\Traits\HasTenantTokens;

class User extends AuthUser implements Authenticatable
{
    use HasTenants;
    use HasTenantTokens;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
