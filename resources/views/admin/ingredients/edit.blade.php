@extends('layouts.app')

@section('title', 'Sửa thành phần')

@section('content')
<div class="card">
    <h1>Sửa thành phần</h1>
    <p><a href="{{ route('admin.ingredients.index') }}">← Danh sách thành phần</a></p>
    <form method="POST" action="{{ route('admin.ingredients.update', $ingredient) }}">
        @csrf @method('PUT')
        @include('admin.ingredients._form')
    </form>
</div>
@endsection
