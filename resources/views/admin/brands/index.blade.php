@extends('layouts.app')

@section('title', 'Quản lý thương hiệu — BeautyPals')

@section('content')
    <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Quản lý Thương hiệu (Brands)</h1>
            <p class="muted">Danh sách các thương hiệu mỹ phẩm chính hãng trên hệ thống.</p>
        </div>
        <a href="{{ route('admin.brands.create') }}" class="btn">+ Thêm thương hiệu</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên thương hiệu</th>
                        <th>Số sản phẩm</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($brands as $brand)
                        <tr>
                            <td>#{{ $brand->id }}</td>
                            <td><strong>{{ $brand->name }}</strong></td>
                            <td>{{ $brand->products_count }}</td>
                            <td style="display: flex; gap: 0.4rem;">
                                <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-ghost btn-sm">Sửa</a>
                                <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $brands->links() }}
    </div>
@endsection

