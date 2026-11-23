<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * S4-02: List platform users with keyword, role, and status filters.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(['customer', 'vendor', 'admin'])],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $role = (string) ($validated['role'] ?? '');
        $status = (string) ($validated['status'] ?? '');

        $users = User::query()
            ->with('vendor')
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
                $pattern = '%'.$escaped.'%';
                $query->where(function ($sub) use ($pattern): void {
                    $sub->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$pattern]);
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'role', 'status'));
    }

    /**
     * S4-02: Lock or unlock a user account with mandatory reason and immutable audit log entry.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ((int) $request->user()->id === (int) $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Bạn không thể tự khóa tài khoản của chính mình.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do khóa hoặc mở khóa tài khoản.',
            'reason.max' => 'Lý do tối đa 255 ký tự.',
        ]);

        $reason = trim($validated['reason']);

        DB::transaction(function () use ($request, $user, $reason): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldStatus = (string) $lockedUser->status;
            $newStatus = $oldStatus === 'locked' ? 'active' : 'locked';

            $lockedUser->update(['status' => $newStatus]);

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
            ->with('success', 'Đã cập nhật trạng thái tài khoản thành công.');
    }
}
