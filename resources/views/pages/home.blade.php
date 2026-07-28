@extends('layouts.app')

@section('content')
    {{-- Hero Section --}}
    <section class="text-center py-20 bg-gradient-to-r from-[#222222] to-[#dc3534] text-white">
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4">
            Send Money Across Africa Instantly
        </h1>
        <p class="text-lg md:text-xl mb-6">
            Fast, secure, and transparent money transfers — from your phone to theirs.
        </p>
        <a href="/services"
           class="bg-[#e9ec3c] text-[#222222] px-6 py-3 rounded-lg font-semibold hover:bg-white transition">
            Our Services
        </a>
    </section>

    {{-- Services Section --}}
    <section class="max-w-6xl mx-auto py-16 px-6">
        <h2 class="text-3xl font-bold text-center text-[#222222] mb-12">How We Help You Move Money</h2>

        <div class="grid md:grid-cols-3 gap-10">
            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">💸</div>
                <h3 class="text-xl font-bold mb-2">Cross-Border Transfers</h3>
                <p class="text-gray-600">
                    Send money to any mobile wallet or bank account across East Africa with competitive rates.
                </p>
            </div>

            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">🔄</div>
                <h3 class="text-xl font-bold mb-2">P2P Crypto Trading</h3>
                <p class="text-gray-600">
                    Buy and sell USDT peer-to-peer with local currency — secure, fast, and wallet-to-wallet.
                </p>
            </div>

            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">📱</div>
                <h3 class="text-xl font-bold mb-2">Mobile Wallet Top-Up</h3>
                <p class="text-gray-600">
                    Fund mobile money wallets directly from your bank or crypto balance in minutes.
                </p>
            </div>
        </div>
    </section>

    {{-- Why Choose Us --}}
    <section class="bg-gray-100 py-16 px-6">
        <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold text-[#222222] mb-6">Why Choose {{ config('app-custom.brand_name') }}?</h2>
                <ul class="space-y-4 text-gray-700">
                    <li>✅ 24/7 Customer Support</li>
                    <li>✅ Real-time transfer tracking from start to finish</li>
                    <li>✅ Competitive exchange rates with no hidden fees</li>
                    <li>✅ Licensed and regulated across East Africa</li>
                </ul>
            </div>
            <div class="bg-white rounded-lg shadow p-8">
                <h3 class="text-xl font-bold text-[#dc3534] mb-4">Quick Facts</h3>
                <p class="mb-2">🌍 <span class="font-bold">5+ Countries</span> covered</p>
                <p class="mb-2">📱 <span class="font-bold">50K+ Transfers</span> processed</p>
                <p class="mb-2">⭐ <span class="font-bold">4.8 / 5</span> user rating</p>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="text-center py-20">
        <h2 class="text-3xl font-bold text-[#222222] mb-6">
            Ready to Send?
        </h2>
        <p class="text-gray-600 mb-6">Start your transfer today — fast, safe, and simple.</p>
        <a href="/tracking"
           class="bg-[#dc3534] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#222222] transition">
            Track a Transfer
        </a>
    </section>
@endsection
