<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class ProspectImportService
{
    public const MAX_ROWS = 1000;

    /** @var array<string,string> */
    private array $headerMap = [
        'nama_perusahaan'=>'company_name','perusahaan'=>'company_name','company'=>'company_name','company_name'=>'company_name',
        'website'=>'website','kota'=>'city','city'=>'city','layanan'=>'service','service'=>'service','rute'=>'route','route'=>'route',
        'ukuran_perusahaan'=>'company_size','company_size'=>'company_size','nama_kontak'=>'contact_name','pic'=>'contact_name',
        'contact_name'=>'contact_name','jabatan_kontak'=>'contact_position','jabatan'=>'contact_position','contact_position'=>'contact_position',
        'telepon_whatsapp'=>'phone','telepon_wa'=>'phone','phone'=>'phone','email'=>'email','sistem_saat_ini'=>'current_system',
        'current_system'=>'current_system','punya_tracking_portal'=>'tracking_portal','tracking_portal'=>'tracking_portal',
        'dugaan_masalah'=>'pain_hypothesis','pain_hypothesis'=>'pain_hypothesis','score_kecocokan'=>'fit_score',
        'skor_kecocokan'=>'fit_score','fit_score'=>'fit_score','score_masalah'=>'pain_score','skor_masalah'=>'pain_score',
        'pain_score'=>'pain_score','score_kemudahan_kontak'=>'contact_score','skor_kemudahan_kontak'=>'contact_score',
        'contact_score'=>'contact_score','status'=>'status','tanggal_follow_up'=>'next_follow_up_at','follow_up'=>'next_follow_up_at',
        'sumber_nama'=>'source_name','source_name'=>'source_name','sumber_url'=>'source_url','source_url'=>'source_url',
        'catatan'=>'notes','notes'=>'notes','email_sales'=>'assigned_sales_email','sales_email'=>'assigned_sales_email',
        'email_account_executive'=>'assigned_sales_email','account_executive_email'=>'assigned_sales_email','email_ae'=>'assigned_sales_email',
        'potensi_deal_rp'=>'estimated_deal_value','potensi_deal'=>'estimated_deal_value','estimated_deal_value'=>'estimated_deal_value',
    ];

    public function __construct(private readonly ImportAssignmentService $assignments) {}

    public function preview(string $path): array
    {
        $rows = $this->readRows($path);
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Maksimal '.self::MAX_ROWS.' baris per import.');
        }

        $result = [];
        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row);
            if ($this->isEmptyRow($normalized)) {
                continue;
            }

            $validator = Validator::make($normalized, $this->rules());
            $companyKey = Prospect::makeCompanyKey($normalized['company_name'] ?? '', $normalized['city'] ?? '');
            $existing = $companyKey ? Prospect::with('assignedUser')->where('company_key', $companyKey)->first() : null;
            $fileSales = $this->assignments->resolveFileSales($normalized['assigned_sales_email'] ?? null);
            $fileSalesWarning = null;
            if (!empty($normalized['assigned_sales_email']) && !$fileSales) {
                $fileSalesWarning = 'Email Account Executive tidak cocok dengan akun Account Executive aktif.';
            }

            $result[] = [
                'row_number' => $index + 2,
                'data' => $normalized,
                'valid' => !$validator->fails(),
                'errors' => $validator->errors()->all(),
                'duplicate' => (bool)$existing,
                'existing_id' => $existing?->id,
                'existing_assigned_to' => $existing?->assigned_to,
                'existing_sales' => $existing?->assignedUser ? $existing->assignedUser->only(['id','name','email']) : null,
                'file_sales' => $fileSales ? $fileSales->only(['id', 'name', 'email']) : null,
                'file_sales_warning' => $fileSalesWarning,
            ];
        }

        return $result;
    }

    /**
     * @param array{mode:string,single_sales_id?:int|null,round_robin_sales_ids?:array<int,int>} $assignmentOptions
     */
    public function import(
        string $path,
        User $actor,
        ImportBatch $batch,
        string $duplicateMode = 'skip',
        array $assignmentOptions = ['mode' => ImportAssignmentService::MODE_FILE],
    ): array {
        $preview = $this->preview($path);
        $summary = [
            'total' => count($preview),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'assignments' => [],
            'unassigned' => 0,
        ];

        $roundRobinIndex = 0;
        foreach ($preview as $item) {
            if (!$item['valid']) {
                $summary['errors'][] = ['row' => $item['row_number'], 'messages' => $item['errors']];
                continue;
            }

            $data = $item['data'];
            $existing = $item['existing_id'] ? Prospect::find($item['existing_id']) : null;
            if ($existing && $duplicateMode === 'skip') {
                $summary['skipped']++;
                continue;
            }

            $assignment = $this->assignments->resolve($data, $assignmentOptions, $roundRobinIndex, $existing);
            $roundRobinIndex = $assignment['next_round_robin_index'];

            $payload = collect($data)->only([
                'company_name','website','city','service','route','company_size','contact_name','contact_position','phone','email',
                'current_system','tracking_portal','pain_hypothesis','fit_score','pain_score','contact_score','status',
                'next_follow_up_at','source_name','source_url','notes','estimated_deal_value',
            ])->toArray();

            $payload['assigned_to'] = $assignment['user_id'];
            $payload['created_by'] = $existing?->created_by ?: $actor->id;
            $payload['import_batch_id'] = $batch->id;

            if ($existing) {
                $existing->update($payload);
                $prospect = $existing->fresh();
                $summary['updated']++;
            } else {
                $prospect = Prospect::create($payload);
                $summary['imported']++;
            }

            if ($prospect->assigned_to) {
                $key = (string)$prospect->assigned_to;
                if (!isset($summary['assignments'][$key])) {
                    $summary['assignments'][$key] = ['count' => 0, 'prospect_ids' => []];
                }
                $summary['assignments'][$key]['count']++;
                $summary['assignments'][$key]['prospect_ids'][] = $prospect->id;
            } else {
                $summary['unassigned']++;
            }
        }

        return $summary;
    }

    /** @return array<int,array<string,mixed>> */
    private function readRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $matrix = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        if (count($matrix) < 2) {
            return [];
        }

        $headers = array_map(fn ($value) => $this->mapHeader((string)$value), $matrix[0]);
        if (!in_array('company_name', $headers, true)) {
            throw new RuntimeException('Kolom "Nama Perusahaan" wajib ada pada baris pertama. Gunakan template Santovate.');
        }

        $rows = [];
        foreach (array_slice($matrix, 1) as $row) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header !== null) {
                    $assoc[$header] = $row[$i] ?? null;
                }
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    private function mapHeader(string $header): ?string
    {
        $normalized = Str::of($header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        return $this->headerMap[$normalized] ?? null;
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            }
            if (($row[$key] ?? null) === '') {
                $row[$key] = null;
            }
        }

        if (!empty($row['website']) && !preg_match('~^https?://~i', $row['website'])) {
            $row['website'] = 'https://'.$row['website'];
        }
        $row['fit_score'] = $this->score($row['fit_score'] ?? 0);
        $row['pain_score'] = $this->score($row['pain_score'] ?? 0);
        $row['contact_score'] = $this->score($row['contact_score'] ?? 0);
        $row['estimated_deal_value'] = max(0, (float)($row['estimated_deal_value'] ?? 0));
        $row['status'] = $this->normalizeStatus($row['status'] ?? 'baru');
        $row['tracking_portal'] = $this->nullableBool($row['tracking_portal'] ?? null);

        if (isset($row['next_follow_up_at']) && is_numeric($row['next_follow_up_at'])) {
            try {
                $row['next_follow_up_at'] = ExcelDate::excelToDateTimeObject((float)$row['next_follow_up_at'])->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $row['next_follow_up_at'] = null;
            }
        }
        return $row;
    }

    private function score(mixed $value): int
    {
        return max(0, min(3, (int)($value ?? 0)));
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = Str::of((string)$value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $aliases = [
            'belum_dicek'=>'baru','baru'=>'baru','sudah_dicek'=>'diriset','sudah_diriset'=>'diriset','diriset'=>'diriset',
            'sudah_dihubungi'=>'dihubungi','dihubungi'=>'dihubungi','ada_balasan'=>'membalas','membalas'=>'membalas',
            'meeting'=>'meeting','demo'=>'demo','proposal'=>'proposal','negosiasi'=>'negosiasi','deal'=>'deal',
            'ditolak'=>'ditolak','tidak_cocok'=>'tidak_cocok',
        ];
        return $aliases[$value] ?? 'baru';
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim((string)$value));
        if (in_array($v, ['1','ya','yes','y','true','ada'], true)) return true;
        if (in_array($v, ['0','tidak','no','n','false','tidak ada'], true)) return false;
        return null;
    }

    private function isEmptyRow(array $row): bool
    {
        return !collect($row)->filter(fn ($v) => $v !== null && $v !== '')->count();
    }

    private function rules(): array
    {
        return [
            'company_name'=>['required','string','max:255'],
            'website'=>['nullable','url','max:255'],
            'city'=>['nullable','string','max:255'],
            'email'=>['nullable','email','max:255'],
            'fit_score'=>['integer','between:0,3'],
            'pain_score'=>['integer','between:0,3'],
            'contact_score'=>['integer','between:0,3'],
            'status'=>['required','in:'.implode(',', array_keys(Prospect::STATUSES))],
            'source_url'=>['nullable','url'],
            'assigned_sales_email'=>['nullable','email'],
            'estimated_deal_value'=>['nullable','numeric','min:0'],
        ];
    }
}
