{{-- Product variants: each with its own price, stock, and image (Shopee/Tokopedia style). --}}
<div class="card space-y-4 p-5">
    <div>
        <h2 class="font-semibold text-gray-900">Varian Produk</h2>
        <p class="text-xs text-gray-400">Untuk 1 produk dengan beberapa pilihan (mis. paket/warna/ukuran). Tiap varian bisa punya harga, stok, dan gambar sendiri. Menambah varian otomatis mengubah tipe produk menjadi "Varian".</p>
    </div>

    @forelse ($product->variants as $variant)
        <form action="{{ route('admin.products.variant.update', $variant) }}" method="POST" enctype="multipart/form-data"
              class="grid items-end gap-3 border-t border-gray-100 pt-4 sm:grid-cols-[72px_1.4fr_1fr_1fr_90px_auto]">
            @csrf @method('PUT')
            <div>
                @if ($variant->image_path)
                    <img src="{{ asset('storage/'.$variant->image_path) }}" alt="{{ $variant->name }}" class="h-16 w-16 rounded-lg border border-gray-200 object-cover">
                @else
                    <div class="grid h-16 w-16 place-items-center rounded-lg border border-dashed border-gray-300 text-[10px] text-gray-400">No img</div>
                @endif
                <input type="file" name="image" accept="image/*" class="mt-1 w-16 text-[10px]">
            </div>
            <div>
                <label class="input-label text-xs">Nama Varian</label>
                <input type="text" name="name" value="{{ $variant->name }}" required class="form-input">
            </div>
            <div>
                <label class="input-label text-xs">Harga Jual (Rp)</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ $variant->price }}" required class="form-input">
            </div>
            <div>
                <label class="input-label text-xs">Harga Coret (opsional)</label>
                <input type="number" step="0.01" min="0" name="sale_price" value="{{ $variant->sale_price }}" placeholder="—" class="form-input">
            </div>
            <div>
                <label class="input-label text-xs">Stok</label>
                <input type="number" min="0" name="stock" value="{{ $variant->stock }}" required class="form-input">
            </div>
            <div class="flex gap-1">
                <button type="submit" class="btn-primary px-3">Simpan</button>
            </div>
        </form>
        <form action="{{ route('admin.products.variant.destroy', $variant) }}" method="POST" class="-mt-2" onsubmit="return confirm('Hapus varian {{ $variant->name }}?')">
            @csrf @method('DELETE')
            <button class="text-xs text-red-500 hover:underline">Hapus varian</button>
        </form>
    @empty
        <p class="text-sm text-gray-400">Belum ada varian. Tambahkan di bawah bila produk ini punya beberapa pilihan.</p>
    @endforelse

    {{-- Add new variant --}}
    <form action="{{ route('admin.products.variant.store', $product) }}" method="POST" enctype="multipart/form-data"
          class="grid items-end gap-3 border-t border-gray-100 pt-4 sm:grid-cols-[72px_1.4fr_1fr_1fr_90px_auto]">
        @csrf
        <div>
            <label class="input-label text-xs">Gambar</label>
            <input type="file" name="image" accept="image/*" class="w-16 text-[10px]">
        </div>
        <div>
            <label class="input-label text-xs">Nama Varian</label>
            <input type="text" name="name" required placeholder="mis. AMAL 4000" class="form-input">
        </div>
        <div>
            <label class="input-label text-xs">Harga Jual (Rp)</label>
            <input type="number" step="0.01" min="0" name="price" required placeholder="0" class="form-input">
        </div>
        <div>
            <label class="input-label text-xs">Harga Coret</label>
            <input type="number" step="0.01" min="0" name="sale_price" placeholder="—" class="form-input">
        </div>
        <div>
            <label class="input-label text-xs">Stok Awal</label>
            <input type="number" min="0" name="stock" value="0" class="form-input">
        </div>
        <div>
            <button type="submit" class="btn-outline px-3">+ Tambah</button>
        </div>
    </form>
</div>
