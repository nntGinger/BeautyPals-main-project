@extends('layouts.app')

@section('title', 'Quản lý người dùng — BeautyPals')

@section('content')
    <div class="card">
        <h1>Quản lý Tài khoản Người dùng</h1>
        <form method="GET" action="{{ route('admin.users.index') }}" class="form-grid" style="margin-top: 1rem;">
            <div>
                <label for="q">Tìm theo tên hoặc email</label>
                <input id="q" type="text" name="q" value="{{ $q }}">
            </div>
            <div>
                <label for="role">Vai trò</label>
                <select id="role" name="role">
                    <option value="">Tất cả</option>
                    @foreach (['customer', 'vendor', 'admin'] as $r)
                        <option value="{{ $r }}" @selected($role === $r)>{{ strtoupper($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected($status === 'active')>ACTIVE</option>
                    <option value="locked" @selected($status === 'locked')>LOCKED</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit">Lọc</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Xóa lọc</a>
            </div>
        </form>
        @error('reason') <p class="error">{{ $message }}</p> @enderror
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Khóa / Mở khóa (kèm lý do)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>#{{ $u->id }}</td>
                            <td><strong>{{ $u->name }}</strong></td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge">{{ strtoupper($u->role) }}</span></td>
                            <td>
                                <span class="badge {{ $u->status === 'active' ? 'badge-ok' : 'badge-danger' }}">
                                    {{ strtoupper($u->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.toggle-status', $u) }}" style="display: flex; gap: 0.4rem; align-items: center;">
                                        @csrf
                                        <label for="reason-{{ $u->id }}" hidden>Lý do</label>
                                        <input id="reason-{{ $u->id }}" type="text" name="reason" placeholder="Lý do..." required style="width: 160px; padding: 0.35rem;">
                                        <button type="submit" class="btn btn-ghost btn-sm">
                                            {{ $u->status === 'locked' ? 'Mở khóa' : 'Khóa' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">Tài khoản hiện tại</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection

