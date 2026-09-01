<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * User and Sales Person administration (requirements 3.1 and 52). Admin only.
 */
class UserController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:manage-users'];
    }

    public function index(Request $request)
    {
        $users = User::query()
            ->search($request->query('search'))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('orders')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users'   => $users,
            'roles'   => UserRole::cases(),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'user'  => new User(['role' => UserRole::SalesPerson, 'is_active' => true]),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['email_verified_at'] = now();

        $user = User::create($data);

        $this->audit->log('user.created', $user, "Created {$user->role->label()} {$user->name}", null, [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role->value,
        ]);

        return redirect()->route('users.index')->with('toast', "{$user->role->label()} {$user->name} created.");
    }

    public function edit(User $user)
    {
        return view('users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $before = $user->getAttributes();

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        // A blank password field means "leave the current password alone".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Never let the last active Admin demote or lock themselves out.
        if ($this->wouldRemoveLastAdmin($user, $data)) {
            return back()->with('error', 'This is the only active Admin. Promote another Admin before changing this account.');
        }

        $user->update($data);

        $this->audit->logChanges('user.updated', $user, $before, "Updated user {$user->name}");

        return redirect()->route('users.index')->with('toast', "{$user->name} updated.");
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->is_active && $this->wouldRemoveLastAdmin($user, ['is_active' => false])) {
            return back()->with('error', 'This is the only active Admin and cannot be deactivated.');
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->log(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            ($user->is_active ? 'Activated' : 'Deactivated') . " user {$user->name}",
        );

        return back()->with('toast', "{$user->name} is now " . ($user->is_active ? 'active' : 'inactive') . '.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->wouldRemoveLastAdmin($user, ['is_active' => false])) {
            return back()->with('error', 'This is the only active Admin and cannot be deleted.');
        }

        // Orders keep pointing at a deleted seller through a soft delete, so
        // historical sales-person reporting is not silently rewritten.
        $name = $user->name;
        $user->delete();

        $this->audit->log('user.deleted', $user, "Deleted user {$name}");

        return redirect()->route('users.index')->with('toast', "{$name} deleted.");
    }

    /** True when the change would leave the system with no active Admin. */
    private function wouldRemoveLastAdmin(User $user, array $data): bool
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return false;
        }

        $stillAdmin = ($data['role'] ?? $user->role->value) === UserRole::Admin->value
            && ($data['is_active'] ?? $user->is_active);

        if ($stillAdmin) {
            return false;
        }

        return User::where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
