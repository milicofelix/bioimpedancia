<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PROFESSIONAL = 'professional';

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_PROFESSIONAL,
        self::ROLE_RECEPTION,
        self::ROLE_VIEWER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
        'last_login_ip',
        'inactivated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'inactivated_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isClinicalProfessional(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_PROFESSIONAL], true);
    }

    public function canCreateClinicalRecords(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_PROFESSIONAL, self::ROLE_RECEPTION], true);
    }

    public function isActive(): bool
    {
        return $this->inactivated_at === null;
    }
}
