@extends('layouts.app')

@section('title', 'Sổ địa chỉ giao hàng — BeautyPals')

@section('content')
    <div class="card">
        <h1>Sổ địa chỉ nhận hàng</h1>
        <p class="muted">Quản lý địa chỉ nhận hàng. Mỗi tài khoản có duy nhất một địa chỉ mặc định.</p>

        <form method="POST" action="{{ route('customer.addresses.store') }}" novalidate>
            @csrf
            <h2>Thêm địa chỉ mới</h2>
            <div class="form-grid">
                <div>
                    <label for="receiver">Họ tên người nhận</label>
                    <input id="receiver" type="text" name="receiver" value="{{ old('receiver') }}" required>
                    @error('receiver') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone">Số điện thoại</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required>
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="line">Số nhà, tên đường</label>
                <input id="line" type="text" name="line" value="{{ old('line') }}" required>
                @error('line') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="form-grid">
                <div>
                    <label for="ward">Phường / Xã</label>
                    <input id="ward" type="text" name="ward" value="{{ old('ward') }}" required>
                    @error('ward') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="district">Quận / Huyện</label>
                    <input id="district" type="text" name="district" value="{{ old('district') }}" required>
                    @error('district') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city">Tỉnh / Thành phố</label>
                    <input id="city" type="text" name="city" value="{{ old('city') }}" required>
                    @error('city') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="margin-top: 0.75rem;">
                <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                    Đặt làm địa chỉ giao hàng mặc định
                </label>
            </div>

            <div style="margin-top: 1rem;">
                <button type="submit">Lưu địa chỉ</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách địa chỉ đã lưu</h2>
        @if ($addresses->isEmpty())
            <p class="muted">Bạn chưa có địa chỉ nhận hàng nào.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Người nhận</th>
                            <th>Số điện thoại</th>
                            <th>Địa chỉ chi tiết</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($addresses as $addr)
                            <tr>
                                <td><strong>{{ $addr->receiver }}</strong></td>
                                <td>{{ $addr->phone }}</td>
                                <td>{{ $addr->line }}, {{ $addr->ward }}, {{ $addr->district }}, {{ $addr->city }}</td>
                                <td>
                                    @if ($addr->is_default)
                                        <span class="badge badge-ok">Mặc định</span>
                                    @else
                                        <span class="badge">Phụ</span>
                                    @endif
                                </td>
                                <td style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                    @if (! $addr->is_default)
                                        <form method="POST" action="{{ route('customer.addresses.default', $addr) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">Đặt mặc định</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('customer.addresses.destroy', $addr) }}">
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
        @endif
    </div>
@endsection

