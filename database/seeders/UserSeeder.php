<?php

namespace Database\Seeders;

use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // --- Staff (demo credentials; disable in production) ---
        $staff = [
            ['Super Admin', 'superadmin@rekasurya.test', 'super-admin'],
            ['Admin Katalog', 'katalog@rekasurya.test', 'admin-katalog'],
            ['Admin Sales', 'sales@rekasurya.test', 'admin-sales'],
            ['Admin Gudang', 'gudang@rekasurya.test', 'admin-gudang'],
            ['Admin Keuangan', 'keuangan@rekasurya.test', 'admin-keuangan'],
        ];

        foreach ($staff as [$name, $email, $roleSlug]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'whatsapp' => '628110000000',
                'is_staff' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            if ($role = Role::where('slug', $roleSlug)->first()) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }

        // --- Customers ---
        $customers = [
            ['Budi Santoso', 'customer@rekasurya.test', 'business', 'CV Surya Mandiri'],
            ['Siti Rahayu', 'siti@example.test', 'personal', null],
            ['Andi Wijaya', 'andi@example.test', 'business', 'PT Andi Energi'],
            ['Dewi Lestari', 'dewi@example.test', 'personal', null],
            ['Rudi Hartono', 'rudi@example.test', 'business', 'Kontraktor RH'],
            ['Maya Putri', 'maya@example.test', 'personal', null],
            ['Agus Setiawan', 'agus@example.test', 'business', 'PT Agus Solar'],
            ['Rina Melati', 'rina@example.test', 'personal', null],
            ['Hendra Gunawan', 'hendra@example.test', 'business', 'CV Hendra Teknik'],
            ['Lisa Anggraini', 'lisa@example.test', 'personal', null],
        ];

        foreach ($customers as $i => [$name, $email, $type, $company]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'whatsapp' => '62812'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'is_staff' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            CustomerProfile::updateOrCreate(['user_id' => $user->id], [
                'company_name' => $company,
                'customer_type' => $type,
                'term_payment_approved' => $type === 'business' && $i < 3,
            ]);

            $user->addresses()->updateOrCreate(['label' => 'Rumah'], [
                'recipient_name' => $name,
                'phone' => $user->whatsapp,
                'company_name' => $company,
                'province' => ['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'Banten'][$i % 4],
                'city' => ['Jakarta Selatan', 'Bandung', 'Surabaya', 'Tangerang'][$i % 4],
                'district' => 'Kecamatan '.($i + 1),
                'postal_code' => '1'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'address_line' => 'Jl. Contoh No. '.($i + 10),
                'is_default' => true,
            ]);
        }
    }
}
