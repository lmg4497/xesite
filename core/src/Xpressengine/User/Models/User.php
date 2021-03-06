<?php
/**
 * User
 *
 * PHP version 7
 *
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2020 Copyright XEHub Corp. <https://www.xehub.io>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        https://xpressengine.io
 */

namespace Xpressengine\User\Models;

use Closure;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Xpressengine\User\Contracts\CanResetPassword as CanResetPasswordContract;
use Xpressengine\User\Notifications\ResetPassword as ResetPasswordNotification;
use Xpressengine\Database\Eloquent\DynamicModel;
use Xpressengine\User\Rating;
use Xpressengine\User\UserInterface;

/**
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2020 Copyright XEHub Corp. <https://www.xehub.io>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        https://xpressengine.io
 */
class User extends DynamicModel implements
    UserInterface,
    AuthenticatableContract,
    CanResetPasswordContract,
    AuthorizableContract
{
    use Notifiable, Authenticatable, Authorizable;

    protected $table = 'user';

    public $incrementing = false;

    /**
     * @var bool use dynamic query
     */
    protected $dynamic = true;

    protected $dates = [
        'password_updated_at',
        'login_at'
    ];

    protected $fillable = [
        'email',
        'login_id',
        'display_name',
        'password',
        'rating',
        'status',
        'introduction',
        'profile_image_id',
        'password_updated_at'
    ];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array
     */
    protected $visible = ['id', 'display_name', 'introduction',];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['profileImage'];

    /**
     * @var \Closure ????????? ????????? ????????? Resolver.
     * ????????? ????????? ???????????? ???????????? ????????? ????????? URL??? ????????????.
     */
    protected static $profileImageResolver;

    /**
     * @var string ???????????? ????????? ????????? ????????? ???, ????????? ???????????? ?????????
     */
    protected $emailForPasswordReset;

    /**
     * @var string getDisplayName()???????????? ????????? ??? ????????? ??????
     */
    public static $displayField = 'display_name';

    const STATUS_ACTIVATED = 'activated';
    const STATUS_DENIED = 'denied';
    const STATUS_PENDING_ADMIN = 'pending_admin';
    const STATUS_PENDING_EMAIL = 'pending_email';

    /**
     * @var array
     */
    public static $status = [
        self::STATUS_DENIED,
        self::STATUS_ACTIVATED,
        self::STATUS_PENDING_ADMIN,
        self::STATUS_PENDING_EMAIL
    ];

    /**
     * User constructor.
     *
     * @param array $attributes attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setProxyOptions(['group' => 'user']);
        $dynamicAttributes = app('xe.dynamicField')->getDynamicAttributes('user');
        $this->makeVisible($dynamicAttributes);
        parent::__construct($attributes);
    }

    /**
     * setProfileImageResolver
     *
     * @param Closure $callback ????????? ????????? ???????????? ???????????? ?????? resolver
     *
     * @return void
     */
    public static function setProfileImageResolver(Closure $callback)
    {
        static::$profileImageResolver = $callback;
    }

    /**
     * set relationship with user groups
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user', 'user_id', 'group_id');
    }

    /**
     * set relationship with us er accounts
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany(UserAccount::class, 'user_id');
    }

    /**
     * set relationship with emails
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emails()
    {
        return $this->hasMany(UserEmail::class, 'user_id');
    }

    /**
     * set relationship with pendingEmail
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pendingEmail()
    {
        return $this->hasOne(PendingEmail::class, 'user_id');
    }

    /**
     * Get profile_image
     *
     * @return string
     */
    public function getProfileImageAttribute()
    {
        return $this->getProfileImage();
    }

    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        // ?????? ???????????? ????????? ???????????? ?????? ????????? ?????? ?????? ??? ???????????? ????????????.
        return isset($this->emailForPasswordReset) ? $this->emailForPasswordReset : $this->email;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string $token token for password reset
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * setEmailForPasswordReset() ??????????????? ????????? email ????????? ????????????.
     *
     * @param string $email ????????? email??????
     *
     * @return void
     */
    public function setEmailForPasswordReset($email)
    {
        $this->emailForPasswordReset = $email;
    }

    /**
     * Get the unique identifier
     *
     * @return string
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the name for display
     *
     * @return string
     */
    public function getDisplayName()
    {
        $field = static::$displayField;
        if (app('xe.config')->getVal('user.register.use_display_name') === false) {
            $field = 'login_id';
        }

        return $this->getAttribute($field);
    }

    /**
     * Get the rating of user
     *
     * @return string
     */
    public function getRating()
    {
        return $this->getAttribute('rating');
    }

    /**
     * Finds whether user has super rating.
     *
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->getRating() === Rating::SUPER;
    }

    /**
     * Finds whether user has manager or super rating.
     *
     * @return boolean
     */
    public function isManager()
    {
        return Rating::compare($this->getRating(), Rating::MANAGER) >= 0;
    }

    /**
     * Get the status of user
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getAttribute('status');
    }

    /**
     * Get profile image URL of user
     *
     * @return string
     */
    public function getProfileImage()
    {
        $resolver = static::$profileImageResolver;
        return $resolver($this->profile_image_id);
    }

    /**
     * Get groups a user belongs
     *
     * @return array
     */
    public function getGroups()
    {
        return $this->getAttribute('groups') ?: [];
    }

    /**
     * Get Pending Email of current user
     *
     * @return PendingEmail
     */
    public function getPendingEmail()
    {
        return $this->pendingEmail;
    }

    /**
     * ????????? ????????? ?????? ?????? ????????? provider??? ?????? ????????? ????????????.
     *
     * @param string $provider provider
     *
     * @return UserAccount
     */
    public function getAccountByProvider($provider)
    {
        foreach ($this->getAttribute('accounts') as $account) {
            if ($account->provider === $provider) {
                return $account;
            }
        }

        return null;
    }

    /**
     * add this user to groups
     *
     * @param mixed $groups groups
     *
     * @return static
     */
    public function joinGroups($groups)
    {
        // todo: increment group's count!!
        $this->groups()->attach($groups);
        return $this;
    }

    /**
     * leave groups
     *
     * @param array $groups groups
     *
     * @return static
     */
    public function leaveGroups(array $groups)
    {
        // todo: decrement group's count!!
        $this->groups()->detach($groups);
        return $this;
    }

    /**
     * ?????? ????????? ????????? ????????????.
     *
     * @param mixed $time ????????? ??????
     *
     * @return void
     */
    public function setLoginTime($time = null)
    {
        if ($time === null) {
            $time = $this->freshTimestamp();
        }
        $this->login_at = $time;
    }

    /**
     * loginAt ??????, loginAt??? ???????????? ????????? ??????, null??? ??????????????? ????????????.
     *
     * @param string $value date time string
     *
     * @return \Carbon\Carbon|null
     */
    public function getLoginAtAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        $at = $this->asDateTime($value);
        if ($at->timestamp <= 0) {
            return null;
        }

        return $at;
    }
}
