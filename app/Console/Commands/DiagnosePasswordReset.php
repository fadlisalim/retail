<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Diagnoses "token invalid" password-reset failures on a live server. The reset
 * logic itself is standard Laravel, so failures are almost always environmental:
 *  - the DB clock and the app clock disagree (timezone skew) → tokens look expired,
 *  - the token lifetime is shorter than the email delivery delay,
 *  - config is cached with stale values.
 *
 * Run on the affected server:  php artisan auth:diagnose-reset you@example.com
 */
class DiagnosePasswordReset extends Command
{
    protected $signature = 'auth:diagnose-reset {email? : Test a real user round-trip}';

    protected $description = 'Diagnose password-reset "token invalid" issues (timezone, expiry, config)';

    public function handle(): int
    {
        $broker = config('auth.defaults.passwords');
        $expire = (int) config("auth.passwords.$broker.expire", 60);

        $this->info('== Konfigurasi ==');
        $this->line('app.timezone      : '.config('app.timezone'));
        $this->line('app.url           : '.config('app.url'));
        $this->line('app.key           : '.(config('app.key') ? 'terset' : 'KOSONG (!)'));
        $this->line('mail default      : '.config('mail.default'));
        $this->line('token expire (min): '.$expire);
        $this->line('config cached     : '.(app()->configurationIsCached() ? 'YA (jalankan config:clear bila baru ubah .env)' : 'tidak'));

        // --- Clock skew: the #1 cause of instant "token invalid". ---
        $this->newLine();
        $this->info('== Jam DB vs Aplikasi ==');
        try {
            $dbNow = Carbon::parse(DB::selectOne('SELECT CURRENT_TIMESTAMP AS n')->n);
            $appNow = now();
            $skew = abs($dbNow->diffInSeconds($appNow));
            $this->line('DB  now : '.$dbNow->toDateTimeString());
            $this->line('App now : '.$appNow->toDateTimeString());
            $this->line('Selisih : '.$skew.' detik');
            if ($skew > 120) {
                $this->error('⚠ Selisih jam DB & aplikasi > 2 menit — ini bisa membuat token dianggap kadaluarsa/tidak valid.');
                $this->line('  Samakan timezone MySQL dengan app (mis. set time_zone, atau APP_TIMEZONE), lalu config:clear.');
            } else {
                $this->line('✓ Jam sinkron.');
            }
        } catch (\Throwable $e) {
            $this->warn('Tidak bisa membaca jam DB: '.$e->getMessage());
        }

        // --- Live round-trip on THIS server's database. ---
        if ($email = $this->argument('email')) {
            $this->newLine();
            $this->info('== Uji Token Nyata: '.$email.' ==');
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error('User dengan email itu tidak ditemukan.');

                return self::FAILURE;
            }

            $token = Password::broker()->createToken($user);
            $row = DB::table('password_reset_tokens')->where('email', $email)->first();
            $this->line('Token dibuat, row created_at: '.($row->created_at ?? 'NULL'));

            $validNow = Password::broker()->tokenExists($user, $token);
            $this->line('Token valid segera setelah dibuat? '.($validNow ? '✓ YA' : '✗ TIDAK'));

            if (! $validNow) {
                $this->error('Token langsung invalid meski baru dibuat → hampir pasti skew jam / timezone DB.');
            } else {
                $this->line('✓ Logika reset di server ini sehat. Jika customer tetap gagal, penyebab paling mungkin:');
                $this->line('  1) Email telat datang > '.$expire.' menit (token keburu kadaluarsa).');
                $this->line('  2) Customer minta reset beberapa kali — hanya link TERBARU yang valid.');
                $this->line('  3) Link di email terpotong (wrap) sehingga token tidak utuh.');
            }

            // Clean up the test token so it can't linger.
            Password::broker()->deleteToken($user);
            $this->line('(token uji dihapus kembali)');
        } else {
            $this->newLine();
            $this->comment('Tip: sertakan email untuk uji nyata → php artisan auth:diagnose-reset customer@email.com');
        }

        return self::SUCCESS;
    }
}
