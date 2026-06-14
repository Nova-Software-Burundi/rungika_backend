@extends('layouts.app')

@section('content')
    <section class="text-center mb-16">
        <h1 class="text-3xl font-extrabold text-[#222222] mb-4">Track Your Transfer</h1>
        <p class="text-lg text-gray-600">
            Enter your transfer reference number to view the latest status and activity.
        </p>
    </section>

    {{-- Tracking Form --}}
    <div class="max-w-lg mx-auto bg-gray-50 p-8 rounded-lg shadow">
        <form method="GET" action="{{ url('/tracking') }}" class="flex gap-3">
            <input
                type="text"
                name="tracking_number"
                placeholder="Enter Reference Number"
                class="flex-1 p-3 border rounded-lg focus:ring-[#dc3534] focus:border-[#dc3534]"
                required
            >
            <button
                type="submit"
                class="bg-[#dc3534] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#222222] transition"
            >
                Track
            </button>
        </form>
    </div>

    {{-- Dummy Tracking Results --}}
    @if(request('tracking_number'))
        <div class="mt-16 max-w-2xl mx-auto">
            <div class="p-6 border rounded-lg shadow bg-white">
                <h2 class="text-2xl font-bold text-[#222222] mb-4">
                    Transfer Results for <span class="text-[#dc3534]">{{ request('tracking_number') }}</span>
                </h2>

                {{-- Transfer Overview --}}
                <div class="mb-6">
                    <p><span class="font-bold">Status:</span> <span class="text-green-600">In Progress</span></p>
                    <p><span class="font-bold">Amount:</span> 500 ZMW</p>
                    <p><span class="font-bold">From:</span> Airtel Money — +260 97 000 0000</p>
                    <p><span class="font-bold">To:</span> Bank of Zambia — 1234567890</p>
                    <p><span class="font-bold">Estimated Completion:</span> Jun 15, 2026</p>
                </div>

                {{-- Tracking Steps --}}
                <div class="relative border-l-2 border-[#e9ec3c] pl-6 space-y-6">
                    <div>
                        <span class="absolute -left-3 w-6 h-6 bg-[#dc3534] rounded-full border-2 border-white"></span>
                        <p class="font-semibold text-[#222222]">Sender Funded</p>
                        <p class="text-sm text-gray-600">Jun 14, 2026 – 09:15</p>
                    </div>

                    <div>
                        <span class="absolute -left-3 w-6 h-6 bg-[#e9ec3c] rounded-full border-2 border-white"></span>
                        <p class="font-semibold text-[#222222]">Processing</p>
                        <p class="text-sm text-gray-600">Jun 14, 2026 – 09:20 — FX conversion completed</p>
                    </div>

                    <div>
                        <span class="absolute -left-3 w-6 h-6 bg-gray-300 rounded-full border-2 border-white"></span>
                        <p class="font-semibold text-[#222222]">Recipient Payout</p>
                        <p class="text-sm text-gray-600">Expected: Jun 15, 2026</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
