@extends('layouts.app')

@section('title', 'BeautyPals — Chăm chút vẻ đẹp của riêng bạn')

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div>
            <p class="eyebrow">Beauty begins with you</p>
            <h1 id="hero-title">Chăm chút vẻ đẹp.<br><em>Theo cách của bạn.</em></h1>
            <p class="hero-copy">Một không gian dành cho những người yêu làm đẹp. Kết nối với các cửa hàng mỹ phẩm và bắt đầu hành trình chăm sóc bản thân cùng BeautyPals.</p>
            <div class="hero-actions">
                @guest
                    <a class="btn" href="{{ route('register') }}">Bắt đầu cùng BeautyPals <span aria-hidden="true">↗</span></a>
                    <a class="btn btn-ghost" href="{{ route('login') }}">Đăng nhập</a>
                @else
                    <a class="btn" href="{{ auth()->user()->homePath() }}">Vào khu vực của tôi <span aria-hidden="true">↗</span></a>
                @endguest
            </div>
            <p class="hero-note">Một tài khoản. Một hành trình làm đẹp của riêng bạn.</p>
        </div>
        <div class="beauty-scene" role="img" aria-label="Minh họa chai serum và hũ kem BeautyPals trên bục màu kem">
            <span class="scene-label">THE BEAUTY OF EVERYDAY</span>
            <div class="plinth"></div>
            <div class="bottle"><div class="product-label">BeautyPals<small>DAILY RITUAL<br><br>BOTANICAL SERUM</small></div></div>
            <div class="jar"><div class="product-label">BeautyPals<small>EVERYDAY CARE</small></div></div>
            <span class="scene-caption">A LITTLE CARE. A LITTLE GLOW.</span>
        </div>
    </section>
    <section aria-labelledby="discover-title">
        <div class="section-heading">
            <div><span class="eyebrow">Chào mừng đến BeautyPals</span><h2 id="discover-title">Điểm hẹn cho người yêu làm đẹp</h2></div>
            <p>Từ thói quen chăm sóc mỗi ngày đến những kết nối mới.</p>
        </div>
        <div class="benefits">
            <article class="benefit"><span class="benefit-number">01 /</span><h3>Dành riêng cho bạn</h3><p>Tạo tài khoản khách hàng để bắt đầu trải nghiệm trong không gian của mình.</p></article>
            <article class="benefit"><span class="benefit-number">02 /</span><h3>Kết nối cùng cửa hàng</h3><p>Nơi người yêu mỹ phẩm và các cửa hàng gặp gỡ trong cùng một cộng đồng.</p></article>
            <article class="benefit"><span class="benefit-number">03 /</span><h3>An tâm từ bước đầu</h3><p>Tài khoản được bảo vệ, giúp bạn yên tâm hơn khi bắt đầu hành trình cùng chúng tôi.</p></article>
        </div>
    </section>
    <footer class="page-footer"><span>BeautyPals · Chăm sóc bản thân, mỗi ngày.</span><span>Made for your everyday glow.</span></footer>
@endsection
