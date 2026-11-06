@extends('layouts.app')

@section('title', 'Nhật ký truy vết (Audit Logs) — BeautyPals')

@section('content')
    <div class="card">
        <h1>Nhật ký Truy vết Hệ thống (Audit Logs)</h1>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="form-grid" style="margin-top: 1rem;">
            <div>
                <label for="actor_id">Người thực hiện</label>
                <select id="actor_id" name="actor_id">
                    <option value="">-- Tất cả --</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected($actorId === $actor->id)>
                            {{ $actor->name }} ({{ $actor->role }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="entity_type">Loại thực thể</label>
                <input id="entity_type" type="text" name="entity_type" value="{{ $entityType }}" placeholder="vendor, product, settlement...">
            </div>
            <div>
                <label for="from_date">Từ ngày</label>
                <input id="from_date" type="date" name="from_date" value="{{ $fromDate }}">
            </div>
            <div>
                <label for="to_date">Đến ngày</label>
                <input id="to_date" type="date" name="to_date" value="{{ $toDate }}">
            </div>
            <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit">Lọc nhật ký</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost">Xóa lọc</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thời điểm</th>
                        <th>Người thực hiện</th>
                        <th>Hành động</th>
                        <th>Thực thể</th>
                        <th>Giá trị cũ (Old)</th>
                        <th>Giá trị mới (New)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>#{{ $log->id }}</td>
                            <td>{{ $log->created_at }}</td>
                            <td>{{ $log->actor?->name ?? 'Hệ thống' }}</td>
                            <td><code>{{ $log->action }}</code></td>
                            <td>{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                            <td><code style="font-size: 0.8rem;">{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                            <td><code style="font-size: 0.8rem;">{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '—' }}</code></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">Chưa có dòng nhật ký nào khớp điều kiện.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection

