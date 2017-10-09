<?php

namespace App\Repositories\Backend\Auth;

use App\Models\Auth\Role;
use Illuminate\Support\Facades\DB;
use App\Exceptions\GeneralException;
use App\Repositories\Traits\CacheResults;
use App\Repositories\BaseEloquentRepository;
use App\Events\Backend\Auth\Role\RoleCreated;
use App\Events\Backend\Auth\Role\RoleUpdated;

/**
 * Class RoleRepository.
 */
class RoleRepository extends BaseEloquentRepository
{
    use CacheResults;

    /**
     * @var array
     */
    protected $relationships = ['permissions', 'users'];

    /**
     * @var string
     */
    protected $model = Role::class;

    /**
     * @param array $data
     *
     * @return Role
     * @throws GeneralException
     */
    public function create(array $data) : Role
    {
        if (! isset($data['permissions'])) {
            $data['permissions'] = [];
        }

        //See if the role must contain a permission as per config
        if (config('access.roles.role_must_contain_permission') && count($data['permissions']) == 0) {
            throw new GeneralException(__('exceptions.backend.access.roles.needs_permission'));
        }

        return DB::transaction(function () use ($data) {
            $role = parent::create(['name' => $data['name']]);

            if ($role) {
                if (count($data['permissions'])) {
                    $role->givePermissionTo($data['permissions']);
                }

                event(new RoleCreated($role));

                app()['cache']->forget('spatie.role.cache');

                return $role;
            }

            throw new GeneralException(trans('exceptions.backend.access.roles.create_error'));
        });
    }

    /**
     * @param mixed $id
     * @param array $data
     *
     * @return mixed
     * @throws GeneralException
     */
    public function update($id, array $data)
    {
        $role = Role::findOrFail($id);

        if (! isset($data['permissions'])) {
            $data['permissions'] = [];
        }

        //See if the role must contain a permission as per config
        if (config('access.roles.role_must_contain_permission') && count($data['permissions']) == 0) {
            throw new GeneralException(__('exceptions.backend.access.roles.needs_permission'));
        }

        return DB::transaction(function () use ($role, $data) {
            if ($role->update([
                'name' => $data['name'],
            ])) {
                if (count($data['permissions'])) {
                    $role->syncPermissions($data['permissions']);
                }

                event(new RoleUpdated($role));

                app()['cache']->forget('spatie.role.cache');

                return $role;
            }

            throw new GeneralException(trans('exceptions.backend.access.roles.update_error'));
        });
    }
}
