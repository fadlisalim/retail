<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipe catalog data for a fresh start. Products are hard-deleted (bypassing
 * soft-deletes) which cascades to images/variants/stock/reviews/cart/wishlist
 * at the DB level; order & commission history is preserved (product_id set null).
 * Associated storage files are removed first.
 */
class ResetCatalog extends Command
{
    protected $signature = 'catalog:reset
        {--with-brands : Also delete all brands}
        {--with-categories : Also delete all categories}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all products (optionally brands & categories) and their files';

    public function handle(): int
    {
        $products = DB::table('products')->count();
        $brands = DB::table('brands')->count();
        $categories = DB::table('categories')->count();

        $this->warn('Ini akan MENGHAPUS PERMANEN:');
        $this->line("  • Produk: {$products} (beserta gambar, varian, stok, review, dsb.)");
        if ($this->option('with-brands')) {
            $this->line("  • Brand: {$brands}");
        }
        if ($this->option('with-categories')) {
            $this->line("  • Kategori: {$categories}");
        }
        $this->line('  Riwayat pesanan & komisi TETAP tersimpan (produk pada pesanan lama menjadi kosong).');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan?', false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        // 1) Remove product media files from the public disk.
        $this->deleteFiles('products', 'main_image_path');
        $this->deleteFiles('product_images', 'path');
        $this->deleteFiles('product_documents', 'path');

        // 2) Hard-delete products (DB cascades handle children; nullable FKs are nulled).
        $deleted = DB::table('products')->delete();
        $this->info("Produk dihapus: {$deleted}");

        // 3) Optional brands.
        if ($this->option('with-brands')) {
            $this->deleteFiles('brands', 'logo_path');
            $n = DB::table('brands')->delete();
            $this->info("Brand dihapus: {$n}");
        }

        // 4) Optional categories (self-referencing parent_id → toggle FK checks).
        if ($this->option('with-categories')) {
            $this->deleteFiles('categories', 'image_path');
            $this->deleteFiles('categories', 'banner_path');
            Schema::disableForeignKeyConstraints();
            $n = DB::table('categories')->delete();
            Schema::enableForeignKeyConstraints();
            $this->info("Kategori dihapus: {$n}");
        }

        $this->newLine();
        $this->info('✔ Reset katalog selesai.');

        return self::SUCCESS;
    }

    /** Delete the files referenced by a table column from the public disk. */
    private function deleteFiles(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $paths = DB::table($table)->whereNotNull($column)->pluck($column)
            ->filter()->unique()->all();

        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }
}
