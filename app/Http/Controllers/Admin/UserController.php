<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Observers\AuditableObserver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $q = trim($request->string('q')->toString());

        $users = User::query()
            ->with('vendor')
            ->when(in_array($role, ['customer', 'vendor', 'admin'], true), fn ($query) => $query->where('role', $role))
            ->when(in_array($status, ['active', 'locked'], true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
                $pattern = '%'.$escaped.'%';
                $query->where(function ($sub) use ($pattern): void {
                    $sub->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'role', 'status', 'q'));
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ((int) $request->user()->id === (int) $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Bạn không thể tự khóa tài khoản của chính mình.');
        }

        $validated = $request->validate(
            [
                'reason' => ['required', 'string', 'max:255'],
            ],
            [
                'reason.required' => 'Vui lòng nhập lý do khóa hoặc mở khóa tài khoản.',
            ],
        );

        $reason = trim($validated['reason']);

        DB::transaction(function () use ($request, $user, $reason): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $lockedUser->status;
            $newStatus = $oldStatus === 'locked' ? 'active' : 'locked';

            AuditableObserver::withoutAuditing(fn () => $lockedUser->update([
                'status' => $newStatus,
            ]));

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'action' => $newStatus === 'locked' ? 'user.locked' : 'user.unlocked',
                'entity_type' => 'user',
                'entity_id' => $lockedUser->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus, 'reason' => $reason],
                'ip' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Đã cập nhật trạng thái tài khoản.');
    }
}
