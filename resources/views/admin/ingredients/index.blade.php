@extends('layouts.app')

@section('title', 'Quản lý thành phần')

@push('styles')
<style>
    main { max-width: 1440px; }
    .ingredients-table th, .ingredients-table td { padding: .8rem .75rem; vertical-align: middle; }
    .ingredients-table th:first-child, .ingredients-table td:first-child { padding-left: 0; }
    .ingredients-table th:last-child, .ingredients-table td:last-child { padding-right: 0; }
    .ingredients-table td { overflow-wrap: anywhere; }
    .ingredients-pagination { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.5rem; }
    .ingredients-page-arrow { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; width: 36px; height: 36px; border: 1px solid var(--line); border-radius: 8px; color: var(--accent-dark); font-size: 1.25rem; line-height: 1; }
    a.ingredients-page-arrow:hover { background: var(--bg); text-decoration: none; }
    .ingredients-page-arrow[aria-disabled="true"] { color: var(--muted); opacity: .45; }
    .ingredients-page-summary { text-align: center; font-size: .9rem; }
    @media (max-width: 600px) {
        .ingredients-table { min-width: 640px; }
        .ingredients-pagination { gap: .5rem; }
    }
</style>
@endpush

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <div><h1>Thành phần</h1><p class="muted">Quản lý tên nguyên liệu, tên hiển thị và alias.</p></div>
        <a class="btn" href="{{ route('admin.ingredients.create') }}">Thêm thành phần</a>
    </div>
    <form method="GET" action="{{ route('admin.ingredients.index') }}" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
        <div style="flex:1;min-width:220px"><label for="q">Tìm thành phần</label><input id="q" name="q" value="{{ $q }}" maxlength="150" placeholder="Tên nguyên liệu, tên hiển thị hoặc alias"></div>
        <button type="submit">Tìm</button>
    </form>
    @error('q') <p class="error">{{ $message }}</p> @enderror
    <div style="overflow-x:auto;margin-top:1.5rem">
        <table class="ingredients-table" style="width:100%;border-collapse:collapse;text-align:left">
            <thead><tr><th>Tên nguyên liệu</th><th>Tên hiển thị</th><th>Alias</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($ingredients as $ingredient)
                <tr style="border-top:1px solid var(--line)">
                    <td>{{ $ingredient->inci_name }}</td>
                    <td>{{ $ingredient->display_name }}</td>
                    <td>{{ $ingredient->aliases->pluck('alias')->join(', ') ?: '—' }}</td>
                    <td style="white-space:nowrap">
                        <a href="{{ route('admin.ingredients.edit', $ingredient) }}">Sửa</a>
                        <form method="POST" action="{{ route('admin.ingredients.destroy', $ingredient) }}" style="display:inline" onsubmit="return confirm('Xóa thành phần này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost" style="padding:.4rem .7rem">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="padding:1rem 0">Không tìm thấy thành phần.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($ingredients->hasPages())
        <nav class="ingredients-pagination" aria-label="Phân trang thành phần">
            @if ($ingredients->onFirstPage())
                <span class="ingredients-page-arrow" aria-disabled="true" aria-label="Trang trước">&lt;</span>
            @else
                <a class="ingredients-page-arrow" href="{{ $ingredients->previousPageUrl() }}" rel="prev" aria-label="Trang trước">&lt;</a>
            @endif
            <span class="ingredients-page-summary muted">Showing {{ $ingredients->firstItem() }} to {{ $ingredients->lastItem() }} of {{ $ingredients->total() }} results</span>
            @if ($ingredients->hasMorePages())
                <a class="ingredients-page-arrow" href="{{ $ingredients->nextPageUrl() }}" rel="next" aria-label="Trang sau">&gt;</a>
            @else
                <span class="ingredients-page-arrow" aria-disabled="true" aria-label="Trang sau">&gt;</span>
            @endif
        </nav>
    @endif
</div>
@endsection
