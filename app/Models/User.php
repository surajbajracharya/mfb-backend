<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\BelongsToCompany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'phone', 'avatar', 'bio', 'interests', 'experience_level', 'source',
        'social_provider', 'social_id', 'stripe_customer_id',
        'gdpr_consent', 'gdpr_consent_at', 'company_id',
        'billing_first_name', 'billing_last_name',
        'billing_address_line1', 'billing_address_line2',
        'billing_suburb', 'billing_state', 'billing_postcode', 'billing_country',
        'notification_preferences',
        'privacy_preferences',
        'date_of_birth', 'time_of_birth', 'place_of_birth', 'is_guest',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->username)) {
                $base = \Illuminate\Support\Str::slug($user->name ?: 'user') ?: 'user';
                $slug = $base;
                $i    = 1;
                while (static::withoutGlobalScope('company')->where('username', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $user->username = $slug;
            }
        });
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'gdpr_consent_at'   => 'datetime',
            'last_seen_at'      => 'datetime',
            'is_online'              => 'boolean',
            'is_identity_verified'   => 'boolean',
            'is_guest'               => 'boolean',
            'date_of_birth'          => 'date',
            'password'          => 'hashed',
            'gdpr_consent'      => 'boolean',
            'interests'                  => 'array',
            'notification_preferences'   => 'array',
            'privacy_preferences'        => 'array',
        ];
    }

    /**
     * Route password-reset emails through EmailService so they use the
     * company's SMTP settings and branded header/footer from the DB.
     */
    public function sendPasswordResetNotification($token): void
    {
        $company = $this->company_id ? Company::find($this->company_id) : null;
        $scheme  = app()->environment('local') ? 'http' : 'https';
        $frontendUrl = $company
            ? rtrim($scheme . '://' . $company->domain, '/')
            : rtrim(config('app.frontend_url'), '/');

        \App\Services\EmailService::send($this->email, 'password_reset', [
            '{username}'  => $this->name,
            '{email}'     => $this->email,
            '{reset_url}' => $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($this->email),
            '{site_name}' => \App\Models\Setting::getValue('site_name', config('app.name')),
        ]);
    }

    public function courses() { return $this->hasMany(Course::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function progress() { return $this->hasMany(CourseProgress::class); }
    public function courseProgress() { return $this->hasMany(CourseProgress::class); }
    public function reviews() { return $this->hasMany(CourseReview::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function eventTickets() { return $this->hasMany(EventTicket::class); }
    public function lectureNotes() { return $this->hasMany(LectureNote::class); }
    public function resources() { return $this->hasMany(Resource::class); }
    public function certificates() { return $this->hasMany(Certificate::class); }
    public function resourceViews() { return $this->hasMany(ResourceView::class); }
    public function planSubscriptions() { return $this->hasMany(UserPlanSubscription::class); }
    public function latestVerification() { return $this->hasOne(IdentityVerification::class)->latestOfMany(); }
}
