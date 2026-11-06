@extends('layouts.app')

@section('title', 'Thêm thành phần')

@section('content')
<div class="card">
    <h1>Thêm thành phần</h1>
    <p><a href="{{ route('admin.ingredients.index') }}">← Danh sách thành phần</a></p>
    <form method="POST" action="{{ route('admin.ingredients.store') }}">
        @csrf
        @include('admin.ingredients._form')
    </form>
</div>
@endsection
