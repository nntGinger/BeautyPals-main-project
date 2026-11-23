@extends('layouts.app')

@section('title', 'Quản lý tài khoản người dùng — BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Quản lý tài khoản người dùng</h1>
            <p class="muted">Tìm kiếm, lọc vai trò và khóa/mở khóa tài khoản kèm lý do lưu vết kiểm toán.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">← Bảng điều khiển Admin</a>
    </div>

    <section class="operations-panel">
        <form method="GET" action="{{ route('admin.users.index') }}" class="operations-filter">
            <div>
                <label for="q">Tìm tên / email / SĐT</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Nhập từ khóa...">
            </div>
            <div>
                <label for="role">Vai trò</label>
                <select id="role" name="role">
                    <option value="">Tất cả vai trò</option>
                    @foreach (['customer' => 'Customer', 'vendor' => 'Vendor', 'admin' => 'Admin'] as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="locked" @selected($status === 'locked')>Locked</option>
                </select>
            </div>
            <button type="submit">Lọc</button>
            <a href="{{ route('admin.users.index') }}">Đặt lại</a>
        </form>

        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>Email / SĐT</th>
                        <th>Vai trò</th>
                        <th>Gian hàng</th>
                        <th>Trạng thái</th>
                        <th>Khóa / Mở khóa (Bắt buộc lý do)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $item)
                        <tr>
                            <td>#{{ $item->id }}</td>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>
                                {{ $item->email }}
                                @if ($item->phone)
                                    <small>{{ $item->phone }}</small>
                                @endif
                            </td>
                            <td>{{ strtoupper($item->role) }}</td>
                            <td>{{ $item->vendor?->store_name ?? '—' }}</td>
                            <td>
                                <span class="order-status {{ $item->status === 'locked' ? 'order-status-rejected' : 'order-status-completed' }}">
                                    {{ strtoupper($item->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($item->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.toggle-status', $item) }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                                        @csrf
                                        <label for="reason-{{ $item->id }}" hidden>Lý do</label>
                                        <input
                                            id="reason-{{ $item->id }}"
                                            type="text"
                                            name="reason"
                                            maxlength="255"
                                            placeholder="Nhập lý do..."
                                            required
                                            style="width:180px;padding:.45rem .65rem;"
                                        >
                                        <button type="submit" class="btn {{ $item->status === 'locked' ? '' : 'btn-ghost' }}">
                                            {{ $item->status === 'locked' ? 'Mở khóa' : 'Khóa tài khoản' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">Tài khoản đang đăng nhập</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="operations-empty">Không tìm thấy tài khoản nào phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links('vendor.partials.pagination') }}
    </section>
@endsection

