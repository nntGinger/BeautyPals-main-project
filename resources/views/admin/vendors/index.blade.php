@extends('layouts.app')

@section('title', 'Kiểm duyệt cửa hàng (Vendors) — BeautyPals')

@section('content')
    <div class="card">
        <h1>Quản lý & Kiểm duyệt Gian hàng (Vendors)</h1>
        <form method="GET" action="{{ route('admin.vendors.index') }}" class="form-grid" style="margin-top: 1rem;">
            <div>
                <label for="q">Tìm theo tên cửa hàng / MST</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Tên gian hàng, MST...">
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach (['pending', 'approved', 'rejected', 'suspended'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit">Lọc</button>
                <a href="{{ route('admin.vendors.index') }}" class="btn btn-ghost">Xóa lọc</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên gian hàng</th>
                        <th>Chủ sở hữu</th>
                        <th>Mã số thuế</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $v)
                        <tr>
                            <td>#{{ $v->id }}</td>
                            <td><strong>{{ $v->store_name }}</strong></td>
                            <td>{{ $v->owner?->name }} ({{ $v->owner?->email }})</td>
                            <td><code>{{ $v->tax_code }}</code></td>
                            <td><span class="badge">{{ strtoupper($v->status) }}</span></td>
                            <td>
                                <a href="{{ route('admin.vendors.show', $v) }}" class="btn btn-sm">Xem & Xét duyệt</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">Không có gian hàng nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vendors->links() }}
    </div>
@endsection

