<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\AuthorizationsController;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;


class SocialInfo extends Authenticatable implements JWTSubject
{
    public $fillable = ['avatar', 'nickname', 'gender', 'extra', 'openid', 'unionid', 'type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Rest omitted for brevity

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


}
