<footer class="bg-[#222222] text-white mt-12">
    <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
            <h4 class="text-lg font-bold">{{ config('app-custom.brand_name') }}</h4>
            <p class="text-sm mt-2">
                Moving money across borders with speed, security, and trust.
            </p>
        </div>
        <div>
            <h4 class="text-lg font-bold">Quick Links</h4>
            <ul class="text-sm mt-2 space-y-2">
                <li><a href="/" class="hover:text-[#e9ec3c]">Home</a></li>
                <li><a href="/services" class="hover:text-[#e9ec3c]">Services</a></li>
                <li><a href="/about" class="hover:text-[#e9ec3c]">About</a></li>
                <li><a href="/contact" class="hover:text-[#e9ec3c]">Contact</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-lg font-bold">Contact Us</h4>
            <p class="text-sm mt-2">Email: {{ config('app-custom.support_email') }}</p>
            <p class="text-sm">Phone: {{ config('app-custom.support_phone') }}</p>
        </div>
    </div>
    <div class="text-center py-4 bg-black text-xs">
        © {{ date('Y') }} {{ config('app-custom.brand_name') }}. All rights reserved.
    </div>
</footer>
