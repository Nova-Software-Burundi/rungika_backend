@extends('layouts.app')

@section('content')
    {{-- Hero Section with Image --}}
    <section class="relative text-center py-28 text-white">
        <!-- Background image -->
        <div class="absolute inset-0">
            <img src="https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
                 alt="Money Transfer" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-[#222222]/80"></div>
        </div>

        <div class="relative z-10 max-w-3xl mx-auto px-6">
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">
                Move Money Across Africa’s Supply Chain
            </h1>
            <p class="text-lg md:text-2xl mb-8">
                Send, receive, and trade — <br class="hidden md:block">
                with speed, security, and zero hassle.
            </p>
            <a href="/services"
               class="bg-[#e9ec3c] text-[#222222] px-8 py-4 rounded-lg font-semibold shadow-lg hover:bg-white transition">
                Our Services
            </a>
        </div>
    </section>

    {{-- Services Section --}}
    <section class="max-w-6xl mx-auto py-20 px-6">
        <h2 class="text-3xl font-bold text-center text-[#222222] mb-12">Our Services</h2>

        <div class="grid md:grid-cols-3 gap-10">
            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">💸</div>
                <h3 class="text-xl font-bold mb-2">Money Transfers</h3>
                <p class="text-gray-600">
                    Send funds across borders to mobile wallets and bank accounts with real-time tracking.
                </p>
            </div>

            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">🔄</div>
                <h3 class="text-xl font-bold mb-2">P2P Crypto Exchange</h3>
                <p class="text-gray-600">
                    Trade USDT peer-to-peer in your local currency with escrow protection and fast settlement.
                </p>
            </div>

            <div class="p-6 bg-gray-50 rounded-lg shadow hover:shadow-lg transition">
                <div class="text-[#dc3534] text-4xl mb-4">📱</div>
                <h3 class="text-xl font-bold mb-2">Mobile Money</h3>
                <p class="text-gray-600">
                    Deposit and withdraw directly from mobile money — Airtel Money, MTN, M-Pesa, and more.
                </p>
            </div>
        </div>
    </section>

    {{-- Why Choose Us --}}
    <section class="bg-gray-100 py-20 px-6">
        <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold text-[#222222] mb-6">Why Rungika?</h2>
                <ul class="space-y-4 text-gray-700">
                    <li>✅ 24/7 live support</li>
                    <li>✅ Real-time transfer status updates</li>
                    <li>✅ Best exchange rates with transparent fees</li>
                    <li>✅ Regulated platform trusted across Africa</li>
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
    <section class="text-center py-20 bg-gradient-to-r from-[#dc3534] to-[#222222] text-white">
        <h2 class="text-3xl font-bold mb-6">
            Ready to Send?
        </h2>
        <p class="text-lg mb-6">Start your transfer in minutes — fast, safe, and simple.</p>
        <a href="/tracking"
           class="bg-[#e9ec3c] text-[#222222] px-8 py-4 rounded-lg font-semibold hover:bg-white transition">
            Track a Transfer
        </a>
    </section>
@endsection
