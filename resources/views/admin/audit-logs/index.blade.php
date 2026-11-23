@extends('layouts.app')

@section('title', 'Nhật ký kiểm toán (Audit Logs) — BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Nhật ký kiểm toán hệ thống (Audit Logs)</h1>
            <p class="muted">Tra cứu lịch sử thao tác quản trị, khóa tài khoản, duyệt cửa hàng và giao dịch thanh toán.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">← Bảng điều khiển Admin</a>
    </div>

    <section class="operations-panel">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="operations-filter">
            <div>
                <label for="actor_user_id">Người thực hiện</label>
                <select id="actor_user_id" name="actor_user_id">
                    <option value="">Tất cả người dùng</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected($actorId === $actor->id)>{{ $actor->name }} ({{ $actor->role }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action">Hành động</label>
                <select id="action" name="action">
                    <option value="">Tất cả hành động</option>
                    @foreach ($actions as $act)
                        <option value="{{ $act }}" @selected($action === $act)>{{ $act }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="auditable_type">Loại thực thể</label>
                <select id="auditable_type" name="auditable_type">
                    <option value="">Tất cả thực thể</option>
                    @foreach ($entityTypes as $type)
                        <option value="{{ $type }}" @selected($entityType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="from">Từ ngày</label>
                <input id="from" type="date" name="from" value="{{ $from }}">
            </div>
            <div>
                <label for="to">Đến ngày</label>
                <input id="to" type="date" name="to" value="{{ $to }}">
            </div>
            <button type="submit">Lọc nhật ký</button>
            <a href="{{ route('admin.audit-logs.index') }}">Đặt lại</a>
        </form>

        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thời điểm</th>
                        <th>Người thực hiện</th>
                        <th>Hành động</th>
                        <th>Thực thể</th>
                        <th>Old Values</th>
                        <th>New Values</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>#{{ $log->id }}</td>
                            <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->actor?->name ?? 'Hệ thống' }}</td>
                            <td><code>{{ $log->action }}</code></td>
                            <td><code>{{ $log->entity_type }} #{{ $log->entity_id }}</code></td>
                            <td><code>{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                            <td><code>{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                            <td>{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="operations-empty">Không có dòng nhật ký nào khớp điều kiện lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links('vendor.partials.pagination') }}
    </section>
@endsection

