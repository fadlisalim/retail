{{-- Google tag (Google Ads / GA4) — hanya dirender bila Google tag ID diisi di
     Admin → Pengaturan → Marketing/Iklan. Konversi: klik WhatsApp (lead), form
     kontak Kirana (lead), kirim Permintaan Penawaran (rfq), pesanan dibuat
     (order). Pesanan LUNAS diimpor terpisah sebagai konversi offline (gclid). --}}
@php
    $gads = app(\App\Services\SettingService::class);
    $googleTagId = trim((string) $gads->get('marketing.google_tag_id'));
    $googleLabels = array_filter([
        'lead' => trim((string) $gads->get('marketing.google_ads_label_lead')),
        'rfq' => trim((string) $gads->get('marketing.google_ads_label_rfq')),
        'order' => trim((string) $gads->get('marketing.google_ads_label_order')),
    ]);
@endphp
@if ($googleTagId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleTagId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @js($googleTagId));

        // ecConv('lead'|'rfq'|'order', {value, transaction_id, label}) — event GA4
        // + konversi Google Ads bila label-nya diisi di Pengaturan.
        (function () {
            var tagId = @js($googleTagId), labels = @js((object) $googleLabels), fired = {};
            window.ecConv = function (kind, params) {
                params = params || {};
                var key = kind + ':' + (params.transaction_id || params.label || '');
                if (fired[key]) return; // sekali per halaman per objek
                fired[key] = true;
                var data = { currency: 'IDR' };
                if (params.value != null) data.value = Number(params.value) || 0;
                if (params.transaction_id) data.transaction_id = String(params.transaction_id);
                if (params.label) data.event_label = String(params.label);
                gtag('event', { lead: 'generate_lead', rfq: 'request_quote', order: 'purchase' }[kind] || kind, data);
                if (labels[kind]) gtag('event', 'conversion', Object.assign({ send_to: tagId + '/' + labels[kind] }, data));
            };
            // Semua tombol/link WhatsApp di situs = lead.
            document.addEventListener('click', function (e) {
                var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                if (!a) return;
                var h = a.getAttribute('href') || '';
                if (/wa\.me\/|api\.whatsapp\.com|whatsapp:\/\//i.test(h)) window.ecConv('lead', { label: 'whatsapp' });
            }, true);
            // Form kontak Kirana / chat toko (dipicu dari app.js).
            window.addEventListener('ec:lead', function (e) { window.ecConv('lead', { label: (e.detail && e.detail.via) || 'form' }); });
        })();
    </script>
@endif
