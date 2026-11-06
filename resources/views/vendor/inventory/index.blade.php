@extends('layouts.app')

@section('title', 'Quản lý Tồn kho & Lô hàng (FEFO) — BeautyPals')

@section('content')
    <div class="card">
        <h1>Tồn kho theo SKU & Lô hàng (FEFO)</h1>
        <p class="muted">Theo dõi số lượng tồn thực tế (On Hand), đang giữ chỗ (Reserved), khả dụng (Available) và cảnh báo hạn sử dụng.</p>
    </div>

    <div class="card">
        <h2>Nhập lô hàng mới vào kho</h2>
        <form method="POST" action="{{ route('vendor.inventory.receive') }}" novalidate>
            @csrf
            <div class="form-grid">
                <div>
                    <label for="sku_id">Chọn phân loại sản phẩm (SKU)</label>
                    <select id="sku_id" name="sku_id" required>
                        <option value="">-- Chọn SKU --</option>
                        @foreach ($skus as $sku)
                            <option value="{{ $sku->id }}" @selected(old('sku_id') == $sku->id)>
                                {{ $sku->sku_code }} — {{ $sku->product?->name }} ({{ $sku->variant_name }})
                            </option>
                        @endforeach
                    </select>
                    @error('sku_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="batch_no">Mã lô (Batch No)</label>
                    <input id="batch_no" type="text" name="batch_no" value="{{ old('batch_no') }}" placeholder="VD: LOT-2026-099" required>
                    @error('batch_no') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mfg_date">Ngày sản xuất</label>
                    <input id="mfg_date" type="date" name="mfg_date" value="{{ old('mfg_date') }}" required>
                    @error('mfg_date') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="exp_date">Hạn sử dụng</label>
                    <input id="exp_date" type="date" name="exp_date" value="{{ old('exp_date') }}" required>
                    @error('exp_date') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="qty">Số lượng nhập</label>
                    <input id="qty" type="number" name="qty" min="1" value="{{ old('qty', 50) }}" required>
                    @error('qty') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <button type="submit">Nhập kho lô mới</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Tổng hợp tồn kho theo SKU (Chỉ tính các lô còn hạn & đang hoạt động)</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã SKU</th>
                        <th>Sản phẩm</th>
                        <th>Phân loại</th>
                        <th>Tồn thực tế (On Hand)</th>
                        <th>Đang giữ chỗ (Reserved)</th>
                        <th>Khả dụng (Available)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($skus as $sku)
                        <tr>
                            <td><code>{{ $sku->sku_code }}</code></td>
                            <td><strong>{{ $sku->product?->name }}</strong></td>
                            <td>{{ $sku->variant_name }}</td>
                            <td>{{ $sku->calc_on_hand }}</td>
                            <td>{{ $sku->calc_reserved }}</td>
                            <td>
                                @if ($sku->calc_available > 0)
                                    <span class="badge badge-ok">{{ $sku->calc_available }}</span>
                                @else
                                    <span class="badge badge-danger">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">Chưa có SKU nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Danh sách chi tiết các lô hàng (Sắp xếp theo FEFO: Hạn gần nhất trước)</h2>
        @error('delta_qty') <p class="error">{{ $message }}</p> @enderror
        @error('reason') <p class="error">{{ $message }}</p> @enderror

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã lô</th>
                        <th>SKU / Sản phẩm</th>
                        <th>NSX</th>
                        <th>HSD</th>
                        <th>Đã nhập</th>
                        <th>On Hand</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>Trạng thái hạn</th>
                        <th>Điều chỉnh tồn</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        @php
                            $expStr = $batch->exp_date->toDateString();
                            $isExpired = $batch->status === 'expired' || $expStr < $today;
                            $isExpiringSoon = ! $isExpired && $expStr <= $soonThreshold;
                            $avail = max(0, (int) $batch->qty_on_hand - (int) $batch->qty_reserved);
                        @endphp
                        <tr>
                            <td><code>{{ $batch->batch_no }}</code></td>
                            <td>
                                <strong>{{ $batch->sku?->sku_code }}</strong><br>
                                <span class="muted" style="font-size: 0.84rem;">{{ $batch->sku?->product?->name }}</span>
                            </td>
                            <td>{{ $batch->mfg_date->format('d/m/Y') }}</td>
                            <td><strong>{{ $batch->exp_date->format('d/m/Y') }}</strong></td>
                            <td>{{ $batch->qty_received }}</td>
                            <td>{{ $batch->qty_on_hand }}</td>
                            <td>{{ $batch->qty_reserved }}</td>
                            <td><strong>{{ $isExpired ? 0 : $avail }}</strong></td>
                            <td>
                                @if ($isExpired)
                                    <span class="badge badge-danger">Đã hết hạn</span>
                                @elseif ($isExpiringSoon)
                                    <span class="badge badge-warn">Sắp hết hạn (≤ 60 ngày)</span>
                                @else
                                    <span class="badge badge-ok">Còn hạn tốt</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('vendor.inventory.adjust', $batch) }}" style="display: flex; gap: 0.35rem; align-items: center;">
                                    @csrf
                                    <label for="delta-{{ $batch->id }}" hidden>Chênh lệch</label>
                                    <input id="delta-{{ $batch->id }}" type="number" name="delta_qty" placeholder="+/-" style="width: 68px; padding: 0.35rem;" required>
                                    <label for="reason-{{ $batch->id }}" hidden>Lý do</label>
                                    <input id="reason-{{ $batch->id }}" type="text" name="reason" placeholder="Lý do kiểm kê..." style="width: 130px; padding: 0.35rem;" required>
                                    <button type="submit" class="btn btn-ghost btn-sm">Lưu</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">Chưa có lô hàng nào trong kho.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $batches->links() }}
        </div>
    </div>
@endsection

