<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryOgImageController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductOgImageController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SiteChatController;
use App\Http\Controllers\WablasWebhookController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront (public)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/produk', [CatalogController::class, 'index'])->name('products.index');
Route::get('/promo', [CatalogController::class, 'promo'])->name('promo');
Route::get('/produk-baru', [CatalogController::class, 'newest'])->name('products.new');
Route::get('/barang-clearance', [CatalogController::class, 'clearance'])->name('clearance');
// "Barang sisa proyek" is now a product condition, not a category/page — keep the
// old URL alive by redirecting to Clearance.
Route::redirect('/barang-sisa-proyek', '/barang-clearance', 301)->name('surplus');

Route::get('/pencarian', [SearchController::class, 'index'])->name('search');
Route::get('/api/pencarian/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')->name('search.suggest');

// CS chat assistant (Claude-powered, grounded in the catalogue). JSON endpoint
// under /api/* so validation errors render as JSON (see bootstrap/app.php).
Route::post('/api/asisten/tanya', [AssistantController::class, 'chat'])
    ->middleware('throttle:15,1')->name('assistant.chat');
Route::get('/api/asisten/riwayat', [AssistantController::class, 'history'])
    ->middleware('throttle:30,1')->name('assistant.history');
Route::post('/api/asisten/kontak', [AssistantController::class, 'contact'])
    ->middleware('throttle:10,1')->name('assistant.contact');

// Chat Toko (customer ↔ admin, Tokopedia-style; NOT the AI assistant).
Route::post('/api/chat-toko/kirim', [SiteChatController::class, 'send'])
    ->middleware('throttle:20,1')->name('sitechat.send');
Route::get('/api/chat-toko/pesan', [SiteChatController::class, 'messages'])
    ->middleware('throttle:60,1')->name('sitechat.messages');
Route::get('/api/chat-toko/notif', [SiteChatController::class, 'unread'])
    ->middleware('throttle:60,1')->name('sitechat.unread');

// Public documentation gallery — real photos of orders being prepared & shipped.
Route::get('/dokumentasi', [DocumentationController::class, 'index'])->name('documentation');

Route::get('/kategori', [CatalogController::class, 'categories'])->name('categories.index');
// 1200x630 social-share (og:image) card for a category page (see products.og).
Route::get('/kategori/{category:slug}/og.png', CategoryOgImageController::class)->name('categories.og');
Route::get('/kategori/{category:slug}', [CatalogController::class, 'category'])->name('categories.show');
Route::get('/brand', [CatalogController::class, 'brands'])->name('brands.index');
Route::get('/brand/{brand:slug}', [CatalogController::class, 'brand'])->name('brands.show');

// Short-link resolver — e.g. /s/Ab3xYz → redirect to the real (possibly ?ref=) URL.
Route::get('/s/{code}', [ShortLinkController::class, 'resolve'])->name('short.resolve');

// Product detail resolves slug manually (to support old-slug redirects).
Route::get('/produk/{slug}', [ProductController::class, 'show'])->name('products.show');
// 1200x630 social-share (og:image) card for a product — landscape so WhatsApp
// renders the LARGE preview even when the product photo is square.
Route::get('/produk/{product:slug}/og.png', ProductOgImageController::class)->name('products.og');

/* Cart */
Route::controller(CartController::class)->group(function () {
    Route::get('/keranjang', 'index')->name('cart.index');
    Route::get('/keranjang/mini', 'mini')->name('cart.mini');
    Route::post('/keranjang', 'store')->name('cart.store');
    Route::patch('/keranjang/{item}', 'update')->name('cart.update');
    Route::delete('/keranjang/{item}', 'destroy')->name('cart.destroy');
    Route::post('/keranjang/{item}/simpan-nanti', 'saveForLater')->name('cart.save');
    Route::post('/keranjang/{item}/pindah', 'moveToCart')->name('cart.move');
    Route::post('/keranjang/{item}/setujui-kondisi', 'acknowledge')->name('cart.acknowledge');
    Route::post('/keranjang/kupon', 'applyCoupon')->name('cart.coupon');
    Route::delete('/keranjang/kupon', 'removeCoupon')->name('cart.coupon.remove');
    Route::post('/keranjang/catatan', 'note')->name('cart.note');
});

/* Checkout — requires login + a saved shipping address */
Route::controller(CheckoutController::class)->middleware('auth')->group(function () {
    Route::get('/checkout', 'index')->name('checkout.index');
    Route::post('/checkout/ongkir', 'shippingOptions')->name('checkout.shipping');
    Route::post('/checkout', 'store')->middleware('throttle:20,1')->name('checkout.store');
});

/* Orders + invoice (public via non-guessable token) */
Route::get('/pesanan/{order:public_token}', [OrderController::class, 'track'])->name('orders.track');
Route::get('/pesanan/{order:public_token}/bayar', [OrderController::class, 'pay'])->name('orders.pay');
Route::get('/invoice/{invoice:public_token}', [OrderController::class, 'invoice'])->name('invoices.show');
Route::get('/invoice/{invoice:public_token}/pdf', [OrderController::class, 'invoicePdf'])->name('invoices.pdf');

/* Quotation / RFQ */
Route::controller(QuotationController::class)->group(function () {
    Route::get('/permintaan-penawaran', 'create')->name('quotations.create');
    Route::post('/permintaan-penawaran', 'store')->middleware('throttle:10,1')->name('quotations.store');
    Route::get('/penawaran/{quotation:public_token}', 'show')->name('quotations.show');
    Route::post('/penawaran/{quotation:public_token}/setujui', 'approve')->name('quotations.approve');
    Route::post('/penawaran/{quotation:public_token}/tolak', 'reject')->name('quotations.reject');
});

/* Wishlist */
Route::controller(WishlistController::class)->group(function () {
    Route::get('/wishlist', 'index')->name('wishlist.index');
    Route::post('/wishlist/{product:slug}', 'toggle')->name('wishlist.toggle');
    Route::post('/wishlist/berbagi', 'share')->name('wishlist.share');
    Route::get('/wishlist/berbagi/{token}', 'shared')->name('wishlist.shared');
});

/* Comparison */
Route::controller(CompareController::class)->group(function () {
    Route::get('/perbandingan', 'index')->name('compare.index');
    Route::post('/perbandingan/{product:slug}', 'add')->name('compare.add');
    Route::delete('/perbandingan/{product:slug}', 'remove')->name('compare.remove');
    Route::delete('/perbandingan', 'clear')->name('compare.clear');
});

/* Reviews & product Q&A */
Route::post('/produk/{product:slug}/review', [ReviewController::class, 'store'])
    ->middleware('auth')->name('reviews.store');
Route::post('/review/{review}/membantu', [ReviewController::class, 'helpful'])
    ->middleware('auth')->name('reviews.helpful');
Route::post('/review/{review}/laporkan', [ReviewController::class, 'report'])
    ->middleware('auth')->name('reviews.report');
Route::post('/produk/{product:slug}/tanya', [ProductController::class, 'ask'])
    ->middleware(['auth', 'throttle:10,1'])->name('questions.store');

/* Affiliate program (public landing) */
Route::get('/afiliasi', [AffiliateController::class, 'landing'])->name('affiliate.landing');

/* CMS + misc */
Route::post('/newsletter', [ContentController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/artikel', [ContentController::class, 'articles'])->name('articles.index');
Route::get('/artikel/{article:slug}', [ContentController::class, 'article'])->name('articles.show');
Route::get('/faq', [ContentController::class, 'faq'])->name('faq');
Route::get('/halaman/{page:slug}', [ContentController::class, 'page'])->name('pages.show');

/* SEO */
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Payment webhook (CSRF-exempt, signature-verified inside controller)
|--------------------------------------------------------------------------
*/
Route::post('/webhook/pembayaran/{provider}', PaymentWebhookController::class)->name('webhook.payment');

// Wablas incoming-message webhook (CSRF-exempt; shared-token check inside).
Route::post('/webhook/wablas', WablasWebhookController::class)->name('webhook.wablas');
// Friendly status page when the webhook URL is opened in a browser (Wablas POSTs).
Route::get('/webhook/wablas', fn () => response('Webhook Wablas aktif ✅ — endpoint ini menerima POST dari server Wablas, bukan akses browser.', 200)->header('Content-Type', 'text/plain; charset=utf-8'));

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    // Password reset (forgot password)
    Route::get('/lupa-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Email verification — the link & resend must work WITHOUT being logged in,
// because customers verify before they can log in.
Route::get('/verifikasi-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')->name('verification.verify');
Route::post('/verifikasi-email/kirim-ulang', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1')->name('verification.send');
Route::middleware('auth')->group(function () {
    Route::get('/verifikasi-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
});

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('akun')->name('account.')->group(function () {
    Route::get('/', [Account\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [Account\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profil', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [Account\ProfileController::class, 'updatePassword'])->name('password.update');

    Route::resource('alamat', Account\AddressController::class)->except(['show'])->names('addresses');

    Route::get('/pesanan', [Account\OrderController::class, 'index'])->name('orders');
    Route::get('/pesanan/{order:public_token}', [Account\OrderController::class, 'show'])->name('orders.show');

    Route::get('/quotation', [Account\QuotationController::class, 'index'])->name('quotations');
    Route::get('/wishlist', [WishlistController::class, 'account'])->name('wishlist');
    Route::get('/review', [Account\ReviewController::class, 'index'])->name('reviews');
    Route::post('/review', [Account\ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/notifikasi', [Account\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifikasi/{id}/baca', [Account\NotificationController::class, 'read'])->name('notifications.read');

    // Affiliate program
    Route::get('/afiliasi', [AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
    Route::get('/afiliasi/daftar', [AffiliateController::class, 'create'])->name('affiliate.register');
    Route::post('/afiliasi/daftar', [AffiliateController::class, 'store'])->name('affiliate.store');
    Route::put('/afiliasi/rekening', [AffiliateController::class, 'updateBank'])->name('affiliate.bank');
    Route::post('/afiliasi/penarikan', [AffiliateController::class, 'requestPayout'])->name('affiliate.payout');
});

/*
|--------------------------------------------------------------------------
| Admin panel  (auth + staff, then per-permission gates)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::resource('kategori', Admin\CategoryController::class)->names('categories')->except('show');
        Route::resource('brand', Admin\BrandController::class)->names('brands')->except('show');
        Route::resource('produk', Admin\ProductController::class)->names('products')->except('show');
        Route::put('produk/{produk}/cepat', [Admin\ProductController::class, 'quickUpdate'])->name('products.quick');
        Route::post('produk/status-massal', [Admin\ProductController::class, 'bulkStatus'])->name('products.bulk-status');
        Route::resource('atribut', Admin\AttributeController::class)->names('attributes')->except('show');

        // Product media (gallery images, datasheet PDFs, YouTube videos).
        Route::post('produk/{produk}/gambar', [Admin\ProductMediaController::class, 'storeImage'])->name('products.image.store');
        Route::post('produk/{produk}/gambar/{image}/utama', [Admin\ProductMediaController::class, 'setPrimaryImage'])->name('products.image.primary');
        Route::post('produk/{produk}/gambar/urutan', [Admin\ProductMediaController::class, 'reorderImages'])->name('products.image.reorder');
        Route::delete('produk-gambar/{image}', [Admin\ProductMediaController::class, 'destroyImage'])->name('products.image.destroy');
        Route::post('produk/{produk}/dokumen', [Admin\ProductMediaController::class, 'storeDocument'])->name('products.document.store');
        Route::delete('produk-dokumen/{dokumen}', [Admin\ProductMediaController::class, 'destroyDocument'])->name('products.document.destroy');
        Route::post('produk/{produk}/video', [Admin\ProductMediaController::class, 'storeVideo'])->name('products.video.store');
        Route::post('produk/{produk}/video-file', [Admin\ProductMediaController::class, 'storeVideoFile'])->name('products.videofile.store');
        Route::delete('produk-video/{video}', [Admin\ProductMediaController::class, 'destroyVideo'])->name('products.video.destroy');

        // Product variants (own price, stock, image)
        Route::post('produk/{produk}/varian', [Admin\ProductVariantController::class, 'store'])->name('products.variant.store');
        Route::put('produk-varian/{varian}', [Admin\ProductVariantController::class, 'update'])->name('products.variant.update');
        Route::delete('produk-varian/{varian}', [Admin\ProductVariantController::class, 'destroy'])->name('products.variant.destroy');
    });

    Route::middleware('permission:inventory.manage')->group(function () {
        Route::get('/stok', [Admin\StockController::class, 'index'])->name('stock.index');
        Route::post('/stok/{product}/sesuaikan', [Admin\StockController::class, 'adjust'])->name('stock.adjust');
        Route::resource('gudang', Admin\WarehouseController::class)->names('warehouses')->except('show');
    });

    Route::middleware('permission:price.manage')->group(function () {
        Route::resource('kupon', Admin\CouponController::class)->names('coupons')->except('show');

        // Tabel harga & margin ala spreadsheet (inline edit per baris).
        Route::get('/harga', [Admin\PriceController::class, 'index'])->name('prices.index');
        Route::patch('/harga/{produk}', [Admin\PriceController::class, 'update'])->name('prices.update');
    });

    Route::middleware('permission:order.view')->group(function () {
        Route::get('/pesanan', [Admin\OrderController::class, 'index'])->name('orders.index');
        // Manual/marketplace sale entry (Tokopedia, WhatsApp, showroom).
        Route::get('/pesanan-manual', [Admin\OrderController::class, 'create'])->middleware('permission:order.manage')->name('orders.create');
        Route::post('/pesanan-manual', [Admin\OrderController::class, 'store'])->middleware('permission:order.manage')->name('orders.store');
        // Kuitansi = bukti pelunasan, jadi hanya keuangan (payment.manage) yang
        // boleh mencetaknya. Invoice tetap bisa dibuka semua pemegang order.view
        // (mis. sales) lewat tautan invoice pada halaman pesanan.
        Route::get('/pesanan/{order}/kuitansi', [Admin\OrderController::class, 'receipt'])
            ->middleware('permission:payment.manage')->name('orders.receipt');
        // Per-order photo documentation (penyiapan, testing, pengiriman…).
        Route::post('/pesanan/{order}/dokumentasi', [Admin\OrderDocumentationController::class, 'store'])
            ->middleware('permission:order.manage')->name('orders.docs.store');
        Route::post('/pesanan/{order}/dokumentasi/{documentation}/publik', [Admin\OrderDocumentationController::class, 'togglePublic'])
            ->middleware('permission:order.manage')->name('orders.docs.public');
        Route::delete('/pesanan/{order}/dokumentasi/{documentation}', [Admin\OrderDocumentationController::class, 'destroy'])
            ->middleware('permission:order.manage')->name('orders.docs.destroy');
        Route::post('/pesanan/{order}/terima-kasih', [Admin\OrderController::class, 'thanks'])->middleware('permission:order.manage')->name('orders.thanks');
        Route::get('/pesanan/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    });
    Route::middleware('permission:order.manage')->group(function () {
        Route::post('/pesanan/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/pesanan/{order}/ongkir', [Admin\OrderController::class, 'confirmShipping'])->name('orders.shipping');
        Route::post('/pesanan/{order}/kirim', [Admin\OrderController::class, 'ship'])->name('orders.ship');
    });
    Route::middleware('permission:payment.manage')->group(function () {
        Route::post('/pesanan/{order}/verifikasi-bayar', [Admin\OrderController::class, 'verifyPayment'])->name('orders.verify');
    });

    Route::middleware('permission:affiliate.manage')->group(function () {
        Route::get('/afiliasi', [Admin\AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/afiliasi/penarikan', [Admin\AffiliatePayoutController::class, 'index'])->name('affiliates.payouts');
        Route::post('/afiliasi/penarikan/{payout}/setujui', [Admin\AffiliatePayoutController::class, 'approve'])->name('affiliates.payouts.approve');
        Route::post('/afiliasi/penarikan/{payout}/lunas', [Admin\AffiliatePayoutController::class, 'markPaid'])->name('affiliates.payouts.paid');
        Route::post('/afiliasi/penarikan/{payout}/tolak', [Admin\AffiliatePayoutController::class, 'reject'])->name('affiliates.payouts.reject');
        Route::get('/afiliasi/{affiliate}', [Admin\AffiliateController::class, 'show'])->name('affiliates.show');
        Route::get('/afiliasi/{affiliate}/dokumen/{type}', [Admin\AffiliateController::class, 'document'])->name('affiliates.document');
        Route::post('/afiliasi/{affiliate}/verifikasi', [Admin\AffiliateController::class, 'verify'])->name('affiliates.verify');
        Route::post('/afiliasi/{affiliate}/tolak', [Admin\AffiliateController::class, 'reject'])->name('affiliates.reject');
        Route::post('/afiliasi/{affiliate}/tangguhkan', [Admin\AffiliateController::class, 'suspend'])->name('affiliates.suspend');
        Route::post('/afiliasi/{affiliate}/aktifkan', [Admin\AffiliateController::class, 'reactivate'])->name('affiliates.reactivate');
        Route::delete('/afiliasi/{affiliate}', [Admin\AffiliateController::class, 'destroy'])->name('affiliates.destroy');
    });

    Route::middleware('permission:quotation.manage')->group(function () {
        Route::get('/quotation', [Admin\QuotationController::class, 'index'])->name('quotations.index');
        Route::get('/quotation/{quotation}', [Admin\QuotationController::class, 'show'])->name('quotations.show');
        Route::post('/quotation/{quotation}/harga', [Admin\QuotationController::class, 'price'])->name('quotations.price');
        Route::post('/quotation/{quotation}/status', [Admin\QuotationController::class, 'status'])->name('quotations.status');
        Route::post('/quotation/{quotation}/jadikan-pesanan', [Admin\QuotationController::class, 'convert'])->name('quotations.convert');
    });

    Route::middleware('permission:review.moderate')->group(function () {
        Route::get('/review', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/review/{review}/visibilitas', [Admin\ReviewController::class, 'toggleVisibility'])->name('reviews.visibility');
        Route::post('/review/{review}/balas', [Admin\ReviewController::class, 'reply'])->name('reviews.reply');

        // Tanya Jawab produk (permission-nya satu payung dengan moderasi review).
        Route::get('/tanya-jawab', [Admin\QuestionController::class, 'index'])->name('questions.index');
        Route::post('/tanya-jawab/{question}/jawab', [Admin\QuestionController::class, 'answer'])->name('questions.answer');
        Route::post('/tanya-jawab/{question}/visibilitas', [Admin\QuestionController::class, 'toggleVisibility'])->name('questions.visibility');
    });

    Route::middleware('permission:content.manage')->group(function () {
        Route::resource('banner', Admin\BannerController::class)->names('banners')->except('show');
        Route::resource('halaman', Admin\PageController::class)->names('pages')->except('show');
        Route::resource('artikel', Admin\ArticleController::class)->names('articles')->except('show');
        Route::resource('faq', Admin\FaqController::class)->names('faqs')->except('show');
    });

    Route::middleware('permission:customer.manage')->group(function () {
        Route::get('/customer', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customer/{user}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('permission:setting.manage')->group(function () {
        Route::get('/pengaturan', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/pengaturan', [Admin\SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:user.manage')->group(function () {
        Route::resource('user', Admin\UserController::class)->names('users');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit-log', [Admin\AuditController::class, 'index'])->name('audit.index');
    });

    Route::middleware('permission:assistant.view')->group(function () {
        Route::get('/cs-assistant', [Admin\AssistantLogController::class, 'index'])->name('assistant.index');

        // WhatsApp inbox (two-way chat via Wablas webhook + send API).
        Route::get('/wa-chat', [Admin\WaChatController::class, 'index'])->name('wachat.index');
        Route::get('/wa-chat/pesan', [Admin\WaChatController::class, 'messages'])->name('wachat.messages');
        Route::post('/wa-chat/kirim', [Admin\WaChatController::class, 'send'])->middleware('throttle:30,1')->name('wachat.send');
        Route::post('/wa-chat/kirim-media', [Admin\WaChatController::class, 'sendMedia'])->middleware('throttle:20,1')->name('wachat.media');
        Route::get('/trafik', [Admin\TrafficController::class, 'index'])
            ->middleware('permission:traffic.view')->name('traffic.index');
        Route::get('/chat-toko', [Admin\SiteChatController::class, 'index'])->name('sitechat.index');
        Route::get('/chat-toko/pesan', [Admin\SiteChatController::class, 'messages'])->name('sitechat.messages');
        Route::post('/chat-toko/kirim', [Admin\SiteChatController::class, 'send'])->middleware('throttle:30,1')->name('sitechat.send');
    });
});
