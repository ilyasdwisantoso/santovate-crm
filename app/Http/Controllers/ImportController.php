<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\User;
use App\Notifications\ProspectsAssignedNotification;
use App\Services\ImportAssignmentService;
use App\Services\ProspectImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(): Response
    {
        $batches = ImportBatch::with('importer')->latest()->paginate(12);
        $batches->setCollection($batches->getCollection()->map(fn ($batch) => [
            'id' => $batch->id,
            'filename' => $batch->filename,
            'assignment_mode' => $batch->assignment_mode,
            'assignment_summary' => $batch->assignment_summary ?? [],
            'total_rows' => $batch->total_rows,
            'imported_rows' => $batch->imported_rows,
            'updated_rows' => $batch->updated_rows,
            'skipped_rows' => $batch->skipped_rows,
            'error_rows' => $batch->error_rows,
            'importer' => $batch->importer?->only(['id', 'name']),
            'created_at' => $batch->created_at?->toIso8601String(),
        ]));

        return Inertia::render('Imports/Index', ['batches' => $batches]);
    }

    public function template(): BinaryFileResponse
    {
        return response()->download(storage_path('app/templates/santovate_prospect_import_template.xlsx'));
    }

    public function sample(): BinaryFileResponse
    {
        return response()->download(storage_path('app/templates/santovate_prospect_sample_30.xlsx'));
    }

    public function preview(
        Request $request,
        ProspectImportService $importer,
        ImportAssignmentService $assignments,
    ): Response|RedirectResponse {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']], [
            'file.mimes' => 'File harus berformat XLSX, XLS, atau CSV.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $dir = storage_path('app/imports/tmp');
        File::ensureDirectoryExists($dir);
        $token = (string)Str::uuid();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $path = $dir.'/'.$token.'.'.$extension;
        $request->file('file')->move($dir, basename($path));

        try {
            $rows = $importer->preview($path);
        } catch (RuntimeException $e) {
            @unlink($path);
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            @unlink($path);
            report($e);
            return back()->withErrors(['file' => 'File tidak dapat dibaca. Pastikan menggunakan template Santovate.']);
        }

        if (empty($rows)) {
            @unlink($path);
            return back()->withErrors(['file' => 'Tidak ada baris data yang dapat diimpor.']);
        }

        return Inertia::render('Imports/Preview', [
            'rows' => $rows,
            'token' => $token,
            'extension' => $extension,
            'originalName' => $request->file('file')->getClientOriginalName(),
            'salesUsers' => $assignments->activeSales()->map->only(['id', 'name', 'email'])->values(),
            'summary' => [
                'total' => count($rows),
                'valid' => collect($rows)->where('valid', true)->count(),
                'duplicates' => collect($rows)->where('duplicate', true)->count(),
                'errors' => collect($rows)->where('valid', false)->count(),
            ],
        ]);
    }

    public function commit(
        Request $request,
        ProspectImportService $importer,
        ImportAssignmentService $assignments,
    ): RedirectResponse {
        $activeSalesIds = $assignments->activeSales()->pluck('id')->all();

        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'extension' => ['required', 'in:xlsx,xls,csv'],
            'original_name' => ['required', 'string', 'max:255'],
            'duplicate_mode' => ['required', 'in:skip,update'],
            'assignment_mode' => ['required', Rule::in(ImportAssignmentService::MODES)],
            'single_sales_id' => ['nullable', 'integer', Rule::in($activeSalesIds)],
            'round_robin_sales_ids' => ['nullable', 'array'],
            'round_robin_sales_ids.*' => ['integer', 'distinct', Rule::in($activeSalesIds)],
        ]);

        if ($data['assignment_mode'] === ImportAssignmentService::MODE_SINGLE && empty($data['single_sales_id'])) {
            return back()->withErrors(['assignment' => 'Pilih Account Executive tujuan untuk mode assign semua ke satu Account Executive.']);
        }
        if ($data['assignment_mode'] === ImportAssignmentService::MODE_ROUND_ROBIN && empty($data['round_robin_sales_ids'])) {
            return back()->withErrors(['assignment' => 'Pilih minimal satu Account Executive untuk distribusi merata.']);
        }

        $path = storage_path('app/imports/tmp/'.$data['token'].'.'.$data['extension']);
        abort_unless(is_file($path), 404, 'File import sementara sudah tidak tersedia. Silakan upload ulang.');

        $batch = null;
        $summary = null;

        try {
            DB::transaction(function () use ($request, $data, $path, $importer, &$batch, &$summary) {
                $batch = ImportBatch::create([
                    'filename' => $data['original_name'],
                    'assignment_mode' => $data['assignment_mode'],
                    'imported_by' => $request->user()->id,
                ]);

                $summary = $importer->import(
                    $path,
                    $request->user(),
                    $batch,
                    $data['duplicate_mode'],
                    [
                        'mode' => $data['assignment_mode'],
                        'single_sales_id' => $data['single_sales_id'] ?? null,
                        'round_robin_sales_ids' => $data['round_robin_sales_ids'] ?? [],
                    ],
                );

                $distribution = $this->distributionSummary($summary['assignments'], (int)$summary['unassigned']);
                $batch->update([
                    'total_rows' => $summary['total'],
                    'imported_rows' => $summary['imported'],
                    'updated_rows' => $summary['updated'],
                    'skipped_rows' => $summary['skipped'],
                    'error_rows' => count($summary['errors']),
                    'errors' => $summary['errors'] ?: null,
                    'assignment_summary' => $distribution,
                ]);
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['import' => 'Import gagal diproses. Tidak ada perubahan database yang disimpan.']);
        } finally {
            @unlink($path);
        }

        foreach ($summary['assignments'] as $userId => $assignment) {
            $sales = User::query()->where('role', 'sales')->where('is_active', true)->find((int)$userId);
            if ($sales && $assignment['count'] > 0) {
                $sales->notify(new ProspectsAssignedNotification($batch, (int)$assignment['count']));
            }
        }

        return redirect()->route('imports.show', $batch)->with('success', 'Import selesai. Assignment Account Executive dan notifikasi sudah diproses.');
    }

    public function show(ImportBatch $batch): Response
    {
        $batch->load('importer');
        return Inertia::render('Imports/Show', ['batch' => [
            'id' => $batch->id,
            'filename' => $batch->filename,
            'assignment_mode' => $batch->assignment_mode,
            'assignment_summary' => $batch->assignment_summary ?? [],
            'total_rows' => $batch->total_rows,
            'imported_rows' => $batch->imported_rows,
            'updated_rows' => $batch->updated_rows,
            'skipped_rows' => $batch->skipped_rows,
            'error_rows' => $batch->error_rows,
            'errors' => $batch->errors ?? [],
            'importer' => $batch->importer?->only(['id', 'name']),
            'created_at' => $batch->created_at?->toIso8601String(),
        ]]);
    }

    /** @param array<string,array{count:int,prospect_ids:array<int,int>}> $assignments */
    private function distributionSummary(array $assignments, int $unassigned): array
    {
        $users = User::query()->whereIn('id', array_map('intval', array_keys($assignments)))
            ->get(['id', 'name', 'email'])->keyBy('id');

        $sales = [];
        foreach ($assignments as $userId => $item) {
            $user = $users->get((int)$userId);
            if (!$user) continue;
            $sales[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'count' => (int)$item['count'],
            ];
        }

        return [
            'sales' => collect($sales)->sortByDesc('count')->values()->all(),
            'unassigned' => $unassigned,
            'assigned_total' => collect($sales)->sum('count'),
        ];
    }
}
