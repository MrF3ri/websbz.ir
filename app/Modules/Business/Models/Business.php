<?php

namespace App\Modules\Business\Models;

use App\Enums\BusinessStatus;
use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Media\Models\BusinessLogo;
use App\Modules\Menu\Models\Menu;
use App\Modules\Subscription\Models\Subscription;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property BusinessStatus $status
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 *
 * @method static Builder<static> query()
 */
class Business extends Model
{
    /** @use HasFactory<Factory<Business>> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
        'phone',
        'email',
        'address',
        'city',
        'postal_code',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => BusinessStatus::Active->value,
    ];

    /**
     * Resolve the factory outside the default App\\Models namespace.
     *
     * @return Factory<Business>
     */
    protected static function newFactory(): Factory
    {
        return BusinessFactory::new();
    }

    /**
     * Bootstrap the model and register its creation hooks.
     */
    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            if (blank($business->public_id)) {
                $business->public_id = (string) Str::ulid();
            }
        });
    }

    /**
     * Get the business membership records.
     *
     * @return HasMany<BusinessMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    /**
     * Get the users belonging to the business.
     *
     * @return BelongsToMany<User, $this, BusinessMember>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_members')
            ->using(BusinessMember::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /** @return HasOne<BusinessLogo, $this> */
    public function logo(): HasOne
    {
        return $this->hasOne(BusinessLogo::class);
    }

    /**
     * Get the primary menu.
     *
     * @return HasOne<Menu, $this>
     */
    public function menu(): HasOne
    {
        return $this->hasOne(Menu::class);
    }

    /**
     * Get the branches owned by the business.
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the primary branch.
     *
     * @return HasOne<Branch, $this>
     */
    public function primaryBranch(): HasOne
    {
        return $this->hasOne(Branch::class)->where('is_primary', true);
    }

    /**
     * Get the business subscriptions.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
        ];
    }
}
