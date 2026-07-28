@extends('layouts.app')

@section('content')
    <section class="text-center mb-16">
        <h1 class="text-3xl font-extrabold text-[#222222] mb-4">Contact Us</h1>
        <p class="text-lg text-gray-600">We are here to answer your questions and help with your transfers.</p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        {{-- Contact Form --}}
        <div>
            <form action="#" method="POST" class="space-y-6 bg-gray-50 p-6 rounded-lg shadow">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Your Name</label>
                    <input type="text" id="name" name="name" class="w-full mt-2 p-3 border rounded-lg focus:ring-[#dc3534] focus:border-[#dc3534]">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Your Email</label>
                    <input type="email" id="email" name="email" class="w-full mt-2 p-3 border rounded-lg focus:ring-[#dc3534] focus:border-[#dc3534]">
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea id="message" name="message" rows="4" class="w-full mt-2 p-3 border rounded-lg focus:ring-[#dc3534] focus:border-[#dc3534]"></textarea>
                </div>

                <button type="submit" class="bg-[#dc3534] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#222222] transition">
                    Send Message
                </button>
            </form>
        </div>

        {{-- Contact Info --}}
        <div class="space-y-6">
            <div class="p-6 bg-[#222222] text-white rounded-lg">
                <h3 class="text-xl font-bold mb-2">Our Office</h3>
                <p>{!! nl2br(e(config('app-custom.physical_address'))) !!}</p>
            </div>
            <div class="p-6 border rounded-lg shadow">
                <h3 class="text-xl font-bold mb-2">Contact Information</h3>
                <p>Email: <a href="mailto:{{ config('app-custom.support_email') }}" class="text-[#dc3534]">{{ config('app-custom.support_email') }}</a></p>
                <p>Phone: {{ config('app-custom.support_phone') }}</p>
            </div>
        </div>
    </div>
@endsection
