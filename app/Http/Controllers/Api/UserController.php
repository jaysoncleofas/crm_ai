<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('is_active'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('name', 'email', 'created_at', 'last_login_at')
            ->with('roles:id,name')
            ->defaultSort('name')
            ->paginate($this->perPage())
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->except('roles'));
            $user->syncRoles($request->input('roles', ['sales_rep']));

            return $user;
        });

        return UserResource::make($user->refresh()->load('roles'))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return UserResource::make($user->load('roles', 'creator:id,name', 'updater:id,name'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except('roles');

            // An empty password field on edit means "leave it alone".
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $user->update($data);

            // Only someone who can manage users may change role assignments.
            if ($request->has('roles') && $request->user()->can('users.update')) {
                $user->syncRoles($request->input('roles', []));
            }
        });

        return UserResource::make($user->refresh()->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User deactivated and moved to trash.']);
    }

    public function restore(int $user): UserResource
    {
        $record = User::onlyTrashed()->findOrFail($user);
        $this->authorize('restore', $record);

        $record->restore();

        return UserResource::make($record->load('roles'));
    }
}
