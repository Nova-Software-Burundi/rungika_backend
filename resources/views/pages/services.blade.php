@extends('layouts.app')

@section('content')
    <section class="text-center mb-16">
        <h1 class="text-3xl font-extrabold text-[#222222] mb-4">Our Services</h1>
        <p class="text-lg text-gray-600">
            {{ config('app-custom.brand_name') }} offers fast, secure, and affordable money transfer and crypto trading services across Africa.
        </p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
            <h3 class="text-xl font-bold text-[#222222] mb-2">Money Transfers</h3>
            <p class="text-sm text-gray-600">
                Send and receive money across borders to mobile wallets and bank accounts in minutes.
            </p>
        </div>
        <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
            <h3 class="text-xl font-bold text-[#222222] mb-2">P2P Crypto Trading</h3>
            <p class="text-sm text-gray-600">
                Buy and sell USDT peer-to-peer with local currency. Escrow-secured and instant settlement.
            </p>
        </div>
        <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
            <h3 class="text-xl font-bold text-[#222222] mb-2">Mobile Wallet Top-Up</h3>
            <p class="text-sm text-gray-600">
                Top up Airtel Money, MTN, M-Pesa, and other mobile wallets directly from your bank account.
            </p>
        </div>
    </div>

    <div class="mt-16 bg-[#e9ec3c] p-10 rounded-lg text-center">
        <h2 class="text-2xl font-bold text-[#222222] mb-4">Need Help?</h2>
        <p class="mb-6 text-gray-800">Our team is ready to assist with your transfer or answer any questions.</p>
        <a href="/contact" class="bg-[#dc3534] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#222222] transition">
            Contact Us
        </a>
    </div>
@endsection
