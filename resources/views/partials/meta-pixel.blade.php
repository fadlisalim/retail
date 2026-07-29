{{-- Meta (Facebook) Pixel — only rendered when a Pixel ID is configured in
     Admin → Pengaturan → Marketing/Iklan. Base code fires PageView on every
     storefront page; per-page events (ViewContent, Purchase, …) are pushed by
     the views and guard on window.fbq so nothing breaks when the ID is empty. --}}
@php($metaPixelId = trim((string) app(\App\Services\SettingService::class)->get('marketing.meta_pixel_id')))
@if ($metaPixelId !== '')
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @js($metaPixelId));
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1" alt=""></noscript>
@endif
