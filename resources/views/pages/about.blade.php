@extends('layouts.app')

@section('content')
    <section class="text-center mb-16">
        <h1 class="text-3xl font-extrabold text-[#222222] mb-4">About Rungika</h1>
        <p class="text-lg text-gray-600">
            We are committed to providing fast, secure money transfer services that connect people and businesses across borders.
        </p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div>
            <img src="/about-truck.jpg" alt="Money Transfer" class="rounded-lg shadow-lg">
        </div>
        <div>
            <h2 class="text-2xl font-bold text-[#222222] mb-4">Our Mission</h2>
            <p class="text-gray-600 mb-6">
                To make cross-border money movement instant, affordable, and accessible to every African.
            </p>

            <h2 class="text-2xl font-bold text-[#222222] mb-4">Our Vision</h2>
            <p class="text-gray-600">
                To be the most trusted financial platform in Africa, powering seamless value transfer for individuals and businesses.
            </p>
        </div>
    </div>

    <section class="mt-20 text-center">
        <h2 class="text-2xl font-bold text-[#222222] mb-6">Our Values</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
                <h3 class="font-bold mb-2">Trust</h3>
                <p class="text-sm text-gray-600">Every transaction is secure, transparent, and verifiable.</p>
            </div>
            <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
                <h3 class="font-bold mb-2">Speed</h3>
                <p class="text-sm text-gray-600">We leverage technology to make transfers instant, not days.</p>
            </div>
            <div class="p-6 border rounded-lg shadow hover:shadow-lg transition">
                <h3 class="font-bold mb-2">Accessibility</h3>
                <p class="text-sm text-gray-600">Anyone with a phone can send and receive money wherever they are.</p>
            </div>
        </div>
    </section>
@endsection
