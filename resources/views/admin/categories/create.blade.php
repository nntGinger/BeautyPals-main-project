@extends('layouts.app')

@section('title', 'Thêm danh mục — BeautyPals')

@section('content')
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h1>Thêm danh mục mới</h1>
        <form method="POST" action="{{ route('admin.categories.store') }}" novalidate>
            @csrf
            <div>
                <label for="name">Tên danh mục</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="slug">Đường dẫn (Slug — để trống sẽ tự sinh)</label>
                <input id="slug" type="text" name="slug" value="{{ old('slug') }}">
                @error('slug') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="parent_id">Danh mục cha</label>
                <select id="parent_id" name="parent_id">
                    <option value="">-- Danh mục gốc --</option>
                    @foreach ($parents as $p)
                        <option value="{{ $p->id }}" @selected(old('parent_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('parent_id') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
                <button type="submit">Lưu danh mục</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Hủy</a>
            </div>
        </form>
    </div>
@endsection

