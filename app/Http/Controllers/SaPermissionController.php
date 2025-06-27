<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

class SaPermissionController extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    function __construct()
    {
        $this->middleware('permission:permission-list|permission-create|permission-edit|permission-delete', ['only' => ['index']]);
        $this->middleware('permission:permission-show', ['only' => ['show']]);
        $this->middleware('permission:permission-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:permission-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:permission-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): View
    {
        $permissions = Permission::orderBy('id', 'DESC')->paginate(5);
        return view('permissions.index', compact('permissions'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): View
    {
        $roles = Role::get();
        return view('permissions.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|unique:sa_permissions,name',
            'roles' => 'nullable',
        ]);

        $rolesId = [];
        if (!empty($request->input('roles'))) {
            $rolesId = array_map(
                function ($value) {
                    return (int)$value;
                },
                $request->input('roles')
            );
        }

        $permission = Permission::create(['name' => $request->input('name')]);
        $permission->syncRoles($rolesId);

        return redirect()->route('permissions.index')
            ->with('success', 'Permission created successfully');
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): View
    {
        $permission = Permission::find($id);
        $permissionRoles = Role::join('sa_role_has_sa_permissions', 'sa_roles.id', '=', 'sa_role_has_sa_permissions.sa_role_id')
            ->where('sa_role_has_sa_permissions.sa_permission_id', $id)
            ->get();

        return view('permissions.show', compact('permission', 'permissionRoles'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id): View
    {
        $permission = Permission::find($id);
        $roles = Role::get();
        $permissionRoles = DB::table("sa_role_has_sa_permissions")->where("sa_role_has_sa_permissions.sa_permission_id", $id)
            ->pluck('sa_role_has_sa_permissions.sa_role_id', 'sa_role_has_sa_permissions.sa_role_id')
            ->all();
        return view('permissions.edit', compact('permission', 'roles', 'permissionRoles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required',
            'roles' => 'nullable',
        ]);

        $permission = Permission::find($id);
        $permission->name = $request->input('name');
        $permission->save();

        $rolesId = [];
        if (!empty($request->input('roles'))) {
            $rolesId = array_map(
                function ($value) {
                    return (int)$value;
                },
                $request->input('roles')
            );
        }

        $permission->syncRoles($rolesId);

        return redirect()->route('permissions.index')
            ->with('success', 'Role updated successfully');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): RedirectResponse
    {
        DB::table("sa_permissions")->where('id', $id)->delete();
        return redirect()->route('permissions.index')
            ->with('success', 'Permission deleted successfully');
    }
}
