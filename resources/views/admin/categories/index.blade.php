@extends('layouts.app')

@section('title', 'Quản lý danh mục — BeautyPals')

@section('content')
    <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Quản lý Danh mục sản phẩm</h1>
            <p class="muted">Danh mục phân cấp dùng cho phân loại sản phẩm và tính hoa hồng theo ngành hàng.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="btn">+ Thêm danh mục</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên danh mục</th>
                        <th>Slug</th>
                        <th>Danh mục cha</th>
                        <th>Số SP</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $cat)
                        <tr>
                            <td>#{{ $cat->id }}</td>
                            <td><strong>{{ $cat->name }}</strong></td>
                            <td><code>{{ $cat->slug }}</code></td>
                            <td>{{ $cat->parent?->name ?? '— Gốc —' }}</td>
                            <td>{{ $cat->products_count }}</td>
                            <td style="display: flex; gap: 0.4rem;">
                                <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-ghost btn-sm">Sửa</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}">
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
        {{ $categories->links() }}
    </div>
@endsection

