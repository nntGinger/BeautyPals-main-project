@extends('layouts.app')

@section('title', 'Thêm thương hiệu — BeautyPals')

@section('content')
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h1>Thêm thương hiệu mới</h1>
        <form method="POST" action="{{ route('admin.brands.store') }}" novalidate>
            @csrf
            <div>
                <label for="name">Tên thương hiệu</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="logo">Đường dẫn Logo (tùy chọn)</label>
                <input id="logo" type="text" name="logo" value="{{ old('logo') }}">
                @error('logo') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
                <button type="submit">Lưu thương hiệu</button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-ghost">Hủy</a>
            </div>
        </form>
    </div>
@endsection

