<?php

namespace App\Support;

/**
 * Registry for the RFQ intake form: qualification/commercial choices plus the
 * technical questions asked per project type.
 *
 * Rationale: sales can only quote fast when the first submission already
 * carries the deciding facts (project stage, funding, budget band, and the
 * type-specific engineering numbers). Choice fields live in real columns so
 * the admin list can filter/sort on them; the type-specific answers land in
 * the `requirements` JSON column so a new project type doesn't mean a
 * migration.
 */
class QuotationForm
{
    /** Who is asking — shapes tone, documents and margin expectations. */
    public const REQUESTER_ROLES = [
        'owner' => 'Pemilik / pengguna langsung',
        'contractor' => 'Kontraktor / EPC',
        'consultant' => 'Konsultan perencana',
        'government' => 'Panitia pengadaan instansi',
        'liaison' => 'Penghubung',
        'other' => 'Lainnya',
    ];

    /** How much authority the requester has over the decision. */
    public const DECISION_ROLES = [
        'decision_maker' => 'Pengambil keputusan',
        'recommender' => 'Merekomendasikan ke atasan/klien',
        'gathering' => 'Baru mengumpulkan data / banding harga',
    ];

    /** Project stage — the strongest signal of how urgent a quote is. */
    public const PROJECT_STATUSES = [
        'planning' => 'Baru perencanaan / menyusun RAB',
        'budgeted' => 'Anggaran sudah tersedia',
        'tender' => 'Tender / lelang sedang berjalan',
        'awarded' => 'Sudah menang tender / terbit SPK',
        'replacement' => 'Ganti atau perbaiki sistem lama',
    ];

    /** Project type — selects which technical block is shown. */
    public const PROJECT_TYPES = [
        'plts_rumah' => 'PLTS rumah',
        'plts_komersial' => 'PLTS komersial / industri',
        'pompa_air' => 'Pompa air tenaga surya (Pamsimas, irigasi)',
        'pju' => 'PJU / lampu jalan tenaga surya',
        'portable' => 'Portable power / backup listrik',
        'lainnya' => 'Lainnya',
    ];

    /** Funding source drives documents, tax handling and payment flow. */
    public const FUNDING_SOURCES = [
        'private' => 'Dana pribadi / perusahaan',
        'government' => 'APBN / APBD / Dana Desa',
        'grant' => 'CSR / hibah / donor',
        'financing' => 'Leasing / pembiayaan / cicilan',
        'undecided' => 'Belum ditentukan',
    ];

    /** Budget band — keeps the quote realistic without demanding exact numbers. */
    public const BUDGET_RANGES = [
        'under_25' => 'Di bawah Rp 25 juta',
        '25_50' => 'Rp 25 – 50 juta',
        '50_150' => 'Rp 50 – 150 juta',
        '150_500' => 'Rp 150 – 500 juta',
        'over_500' => 'Di atas Rp 500 juta',
        'unknown' => 'Belum ada patokan anggaran',
    ];

    /**
     * Technical questions per project type. Every block ends with an
     * "unknown" escape hatch so a non-technical customer is never blocked.
     *
     * @return array<string, array<int, array{key: string, label: string, type: string, hint?: string, options?: array<int, string>}>>
     */
    public static function technicalFields(): array
    {
        return [
            'plts_rumah' => [
                ['key' => 'pln_power', 'label' => 'Daya PLN terpasang', 'type' => 'select', 'options' => ['900 VA', '1.300 VA', '2.200 VA', '3.500 VA', '5.500 VA', 'Di atas 5.500 VA', 'Belum ada listrik PLN']],
                ['key' => 'monthly_bill', 'label' => 'Tagihan PLN per bulan', 'type' => 'text', 'hint' => 'Contoh: Rp 1,5 juta'],
                ['key' => 'daily_energy', 'label' => 'Perkiraan pemakaian harian (kWh/hari)', 'type' => 'text', 'hint' => 'Kosongkan bila belum tahu'],
                ['key' => 'system_type', 'label' => 'Tipe sistem yang diinginkan', 'type' => 'select', 'options' => ['On-grid (hemat tagihan)', 'Hybrid (hemat + backup saat padam)', 'Off-grid (mandiri penuh)', 'Belum tahu, mohon disarankan']],
                ['key' => 'roof_type', 'label' => 'Jenis atap / lokasi pemasangan', 'type' => 'select', 'options' => ['Genteng beton/keramik', 'Metal / spandek', 'Cor beton', 'Ground mount (di tanah)', 'Belum ditentukan']],
                ['key' => 'backup_priority', 'label' => 'Beban yang wajib tetap nyala saat padam', 'type' => 'text', 'hint' => 'Contoh: kulkas, 1 AC, lampu, WiFi'],
            ],
            'plts_komersial' => [
                ['key' => 'pln_power', 'label' => 'Daya PLN terpasang', 'type' => 'text', 'hint' => 'Contoh: 33 kVA / 100 kVA'],
                ['key' => 'phase', 'label' => 'Sistem kelistrikan', 'type' => 'select', 'options' => ['1 fasa', '3 fasa', 'Belum tahu']],
                ['key' => 'monthly_bill', 'label' => 'Tagihan listrik per bulan', 'type' => 'text'],
                ['key' => 'target_capacity', 'label' => 'Target kapasitas PLTS (kWp)', 'type' => 'text', 'hint' => 'Kosongkan bila minta dihitung tim kami'],
                ['key' => 'roof_area', 'label' => 'Luas atap / lahan tersedia (m²)', 'type' => 'text'],
                ['key' => 'roof_type', 'label' => 'Jenis atap / lahan', 'type' => 'select', 'options' => ['Metal / spandek', 'Cor beton', 'Genteng', 'Ground mount (di tanah)', 'Carport', 'Belum ditentukan']],
                ['key' => 'operating_hours', 'label' => 'Jam operasional beban', 'type' => 'text', 'hint' => 'Contoh: 08.00–17.00, atau 24 jam'],
            ],
            'pompa_air' => [
                ['key' => 'total_head', 'label' => 'Total head / ketinggian angkat (meter)', 'type' => 'text', 'hint' => 'Jarak vertikal sumber air ke tandon'],
                ['key' => 'water_demand', 'label' => 'Kebutuhan air per hari (m³ atau liter)', 'type' => 'text', 'hint' => 'Contoh: 10 m³/hari, atau jumlah KK yang dilayani'],
                ['key' => 'water_source', 'label' => 'Sumber air', 'type' => 'select', 'options' => ['Sumur bor', 'Sumur gali', 'Sungai / danau', 'Mata air', 'Embung / bak', 'Lainnya']],
                ['key' => 'water_depth', 'label' => 'Kedalaman muka air / sumur (meter)', 'type' => 'text'],
                ['key' => 'borehole_diameter', 'label' => 'Diameter casing sumur (inch)', 'type' => 'text', 'hint' => 'Penting untuk memilih pompa submersible'],
                ['key' => 'pipe_distance', 'label' => 'Panjang jalur pipa (meter)', 'type' => 'text'],
                ['key' => 'tank_capacity', 'label' => 'Kapasitas tandon / reservoir', 'type' => 'text'],
                ['key' => 'households', 'label' => 'Jumlah KK / titik layanan', 'type' => 'text'],
            ],
            'pju' => [
                ['key' => 'point_count', 'label' => 'Jumlah titik lampu', 'type' => 'text'],
                ['key' => 'lamp_power', 'label' => 'Daya lampu per titik (watt)', 'type' => 'text', 'hint' => 'Kosongkan bila minta disarankan'],
                ['key' => 'pole_height', 'label' => 'Tinggi tiang (meter)', 'type' => 'select', 'options' => ['5 meter', '6 meter', '7 meter', '8 meter', '9 meter atau lebih', 'Belum ditentukan']],
                ['key' => 'pole_included', 'label' => 'Tiang termasuk dalam pengadaan?', 'type' => 'select', 'options' => ['Ya, sekalian tiang', 'Tidak, tiang sudah ada', 'Belum tahu']],
                ['key' => 'burn_hours', 'label' => 'Lama nyala per malam (jam)', 'type' => 'select', 'options' => ['6 jam', '8 jam', '10 jam', '12 jam (senja–subuh)', 'Belum ditentukan']],
                ['key' => 'lamp_type', 'label' => 'Tipe PJU', 'type' => 'select', 'options' => ['All-in-One', 'Two-in-One (panel terpisah)', 'Konvensional (panel + baterai + SCC terpisah)', 'Belum tahu']],
            ],
            'portable' => [
                ['key' => 'usage', 'label' => 'Dipakai untuk apa', 'type' => 'text', 'hint' => 'Contoh: camping, jualan outdoor, backup rumah, event'],
                ['key' => 'load_watt', 'label' => 'Total daya perangkat (watt)', 'type' => 'text', 'hint' => 'Contoh: kulkas + lampu + laptop ±400W'],
                ['key' => 'runtime', 'label' => 'Perlu tahan berapa lama', 'type' => 'text', 'hint' => 'Contoh: 8 jam, atau 2 hari'],
                ['key' => 'solar_panel', 'label' => 'Sekalian panel surya untuk mengisi?', 'type' => 'select', 'options' => ['Ya', 'Tidak', 'Belum tahu']],
            ],
            'lainnya' => [
                ['key' => 'requirement_summary', 'label' => 'Ringkasan kebutuhan', 'type' => 'text', 'hint' => 'Jelaskan singkat kebutuhan proyek Anda'],
            ],
        ];
    }

    /** Allowed requirement keys for a project type (validation + storage filter). */
    public static function technicalKeys(?string $projectType): array
    {
        return collect(self::technicalFields()[$projectType] ?? [])->pluck('key')->all();
    }

    /** Human label for a stored choice value. */
    public static function label(string $group, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $map = match ($group) {
            'requester_role' => self::REQUESTER_ROLES,
            'decision_role' => self::DECISION_ROLES,
            'project_status' => self::PROJECT_STATUSES,
            'project_type' => self::PROJECT_TYPES,
            'funding_source' => self::FUNDING_SOURCES,
            'budget_range' => self::BUDGET_RANGES,
            default => [],
        };

        return $map[$value] ?? $value;
    }

    /** Label for a technical requirement key, scoped to the project type. */
    public static function technicalLabel(?string $projectType, string $key): string
    {
        foreach (self::technicalFields()[$projectType] ?? [] as $field) {
            if ($field['key'] === $key) {
                return $field['label'];
            }
        }

        // Fall back to any type's definition (project type may have been edited).
        foreach (self::technicalFields() as $fields) {
            foreach ($fields as $field) {
                if ($field['key'] === $key) {
                    return $field['label'];
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Lead temperature for the admin list: hot RFQs are the ones with money
     * and authority already in place. Returns ['label' => …, 'class' => …]
     * with full utility classes (interpolated Tailwind classes aren't scanned).
     */
    public static function priority(\App\Models\Quotation $quotation): ?array
    {
        $score = 0;
        $score += match ($quotation->project_status) {
            'awarded' => 3,
            'tender', 'budgeted' => 2,
            'replacement' => 1,
            default => 0,
        };
        $score += $quotation->decision_role === 'decision_maker' ? 2 : ($quotation->decision_role === 'recommender' ? 1 : 0);
        $score += in_array($quotation->budget_range, ['150_500', 'over_500'], true) ? 2
            : (in_array($quotation->budget_range, ['50_150', '25_50'], true) ? 1 : 0);
        $score += $quotation->funding_source === 'government' ? 1 : 0;

        return match (true) {
            $score >= 6 => ['label' => '🔥 Prioritas tinggi', 'class' => 'bg-red-100 text-red-700'],
            $score >= 3 => ['label' => 'Prioritas sedang', 'class' => 'bg-amber-100 text-amber-700'],
            $score > 0 => ['label' => 'Prioritas rendah', 'class' => 'bg-gray-100 text-gray-600'],
            default => null,
        };
    }
}
