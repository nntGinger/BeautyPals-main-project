@extends('layouts.app')

@section('title', 'Quy tắc hoa hồng — BeautyPals')

@section('content')
    <div class="card">
        <h1>Quản lý Quy tắc Hoa hồng (Commission Rules)</h1>
        <p class="muted">Quy tắc theo danh mục ưu tiên hơn danh mục cha và quy tắc mặc định toàn sàn. Thêm quy tắc mới với ngày hiệu lực, không ghi đè lịch sử.</p>

        <form method="POST" action="{{ route('admin.commissions.store') }}" novalidate>
            @csrf
            <div class="form-grid">
                <div>
                    <label for="category_id">Phạm vi danh mục</label>
                    <select id="category_id" name="category_id">
                        <option value="">-- Mặc định toàn sàn (Platform Default) --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rate">Tỷ lệ hoa hồng (0% – 100%)</label>
                    <input id="rate" type="number" step="0.01" min="0" max="100" name="rate" value="{{ old('rate', '10.00') }}" required>
                    @error('rate') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="effective_from">Ngày bắt đầu hiệu lực</label>
                    <input id="effective_from" type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
                    @error('effective_from') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <button type="submit">Thêm quy tắc hoa hồng</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách quy tắc hiện hành</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Phạm vi danh mục</th>
                        <th>Tỷ lệ (%)</th>
                        <th>Ngày hiệu lực</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td>#{{ $rule->id }}</td>
                            <td>
                                @if ($rule->category)
                                    <strong>{{ $rule->category->name }}</strong>
                                @else
                                    <span class="badge">Mặc định toàn sàn</span>
                                @endif
                            </td>
                            <td><strong>{{ $rule->rate }}%</strong></td>
                            <td>{{ $rule->effective_from->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge {{ $rule->is_active ? 'badge-ok' : 'badge-danger' }}">
                                    {{ $rule->is_active ? 'Đang bật' : 'Tạm ngưng' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rules->links() }}
    </div>
@endsection

