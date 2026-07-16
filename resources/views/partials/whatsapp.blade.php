@if($whatsappEnabled)
    <a href="{{ whatsapp_link($siteSettings->get('whatsapp.greeting', 'Halo Rekasurya, saya ingin berkonsultasi.')) }}"
       target="_blank" rel="noopener"
       class="fixed bottom-6 right-4 z-40 hidden items-center gap-2 rounded-full bg-green-500 px-4 py-3 text-white shadow-lg transition hover:bg-green-600 lg:flex"
       aria-label="Konsultasi via WhatsApp">
        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
        <span class="hidden text-sm font-semibold sm:block">Konsultasi</span>
    </a>
@endif
