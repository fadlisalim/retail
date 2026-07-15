<?php

namespace App\Support;

/**
 * Canonical registry of roles and permissions. The RoleSeeder materialises these
 * into the roles / permissions / permission_role tables so RBAC is data-driven,
 * while this class keeps the vocabulary type-safe and greppable in code.
 */
final class Rbac
{
    // Roles (slug => label)
    public const ROLES = [
        'super-admin' => 'Super Admin',
        'admin-katalog' => 'Admin Katalog',
        'admin-sales' => 'Admin Sales',
        'admin-gudang' => 'Admin Gudang',
        'admin-keuangan' => 'Admin Keuangan',
        'customer-service' => 'Customer Service',
        'admin-konten' => 'Admin Konten',
    ];

    // Permissions (slug => label) — granular actions checked by policies/gates.
    public const PERMISSIONS = [
        'dashboard.view' => 'Lihat Dashboard',
        'catalog.manage' => 'Kelola Katalog (produk, kategori, brand, atribut)',
        'inventory.manage' => 'Kelola Stok & Gudang',
        'price.manage' => 'Kelola Harga & Promo',
        'order.view' => 'Lihat Pesanan',
        'order.manage' => 'Proses Pesanan',
        'payment.manage' => 'Kelola Pembayaran',
        'shipping.manage' => 'Kelola Pengiriman',
        'quotation.manage' => 'Kelola Quotation',
        'customer.manage' => 'Kelola Customer',
        'review.moderate' => 'Moderasi Review & Tanya Jawab',
        'return.manage' => 'Kelola Retur',
        'content.manage' => 'Kelola Konten (banner, halaman, artikel, FAQ)',
        'setting.manage' => 'Kelola Pengaturan',
        'user.manage' => 'Kelola User Admin & Role',
        'audit.view' => 'Lihat Audit Log',
        'affiliate.manage' => 'Kelola Afiliasi & Komisi',
    ];

    /** Which permissions each role receives by default. */
    public const ROLE_PERMISSIONS = [
        'super-admin' => ['*'], // all permissions
        'admin-katalog' => ['dashboard.view', 'catalog.manage', 'price.manage', 'review.moderate'],
        'admin-sales' => ['dashboard.view', 'order.view', 'order.manage', 'quotation.manage', 'customer.manage'],
        'admin-gudang' => ['dashboard.view', 'inventory.manage', 'order.view', 'shipping.manage'],
        'admin-keuangan' => ['dashboard.view', 'order.view', 'payment.manage', 'affiliate.manage'],
        'customer-service' => ['dashboard.view', 'order.view', 'review.moderate', 'customer.manage', 'return.manage'],
        'admin-konten' => ['dashboard.view', 'content.manage'],
    ];

    public static function permissionsFor(string $roleSlug): array
    {
        $granted = self::ROLE_PERMISSIONS[$roleSlug] ?? [];

        if (in_array('*', $granted, true)) {
            return array_keys(self::PERMISSIONS);
        }

        return $granted;
    }
}
