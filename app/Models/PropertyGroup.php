<?php

namespace App\Models;

use App\Models\Concerns\HasAdminGroupScope;
use App\Models\Scopes\AdminGroupScope;
use Illuminate\Database\Eloquent\Model;

class PropertyGroup extends Model
{
    use HasAdminGroupScope;

    protected const ADMIN_GROUP_SCOPE_MODE = AdminGroupScope::DIRECT;

    protected const ADMIN_GROUP_SCOPE_KEY = 'id';

    protected $table = 'groups';

    protected $fillable = ['name', 'responsible_name', 'phone', 'pix_key'];

    public function properties()
    {
        return $this->hasMany(Property::class, 'group_id');
    }

    public function rules()
    {
        return $this->hasMany(CondominiumRule::class, 'group_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'group_id');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'group_id');
    }
}
