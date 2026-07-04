<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;

/**
 * Thin wrapper around the Google Drive API for uploading order files.
 *
 * Uses a Service Account (JSON key) — the same account that was used in the
 * old CRM. Files are uploaded to the same Google Drive folders so old and
 * new files stay together in one place.
 *
 * Folder IDs (set in config/services.php → google_drive.folders.*):
 *   specification  → Google Drive folder for specifications
 *   supplier       → Google Drive folder for supplier invoices + paid invoices
 *   commercial     → Google Drive folder for commercial offers
 *
 * Public access (anyone/reader) is set on every uploaded file so the
 * generated drive.google.com/file/d/{id}/view link works without login.
 */
class GoogleDriveService
{
    private Drive $driveService;

    /** Google Drive folder IDs — mirrors the old CRM's folder structure. */
    private array $folderIds = [
        'specification'    => '1PFX8BRwDS6_xkB2pbBXZBkP1m6WEe5J1',
        'supplier_invoice' => '1cMA1yp9AwmiSrHc0_JsB_KVH4a7VJOo7',
        'paid_invoice'     => '1cMA1yp9AwmiSrHc0_JsB_KVH4a7VJOo7', // same folder as supplier_invoice
        'commercial'       => '1LtJfwB0KJxFMoXdevdJYO6TJYZxMsC1_',
        'other'            => '1cMA1yp9AwmiSrHc0_JsB_KVH4a7VJOo7', // fallback
    ];

    public function __construct()
    {
        $keyPath = config('services.google_drive.key_path');

        $client = new Client();
        $client->setAuthConfig($keyPath);
        $client->addScope(Drive::DRIVE);

        $this->driveService = new Drive($client);
    }

    /**
     * Upload a file to Google Drive from an UploadedFile instance.
     *
     * @param  UploadedFile  $file       The uploaded file from the form
     * @param  string        $type       OrderFile type (specification, supplier_invoice, etc.)
     * @param  int           $orderId    Used to build a descriptive file name
     * @param  string|null   $extra      Optional extra context for the filename (e.g. supplier number)
     * @return array{url: string, file_name: string}
     */
    public function upload(UploadedFile $file, string $type, int $orderId, ?string $extra = null): array
    {
        return $this->uploadRaw(
            content: file_get_contents($file->getRealPath()),
            mimeType: $file->getMimeType() ?: 'application/octet-stream',
            extension: $file->getClientOriginalExtension(),
            type: $type,
            orderId: $orderId,
            extra: $extra,
        );
    }

    /**
     * Upload a file to Google Drive from a local filesystem path.
     * Used when Livewire has already persisted the file to temporary storage.
     *
     * @param  string  $localPath      Absolute path on local disk
     * @param  string  $originalName   Original filename (for extension detection)
     * @return array{url: string, file_name: string}
     */
    public function uploadFromPath(string $localPath, string $originalName, string $type, int $orderId, ?string $extra = null): array
    {
        return $this->uploadRaw(
            content: file_get_contents($localPath),
            mimeType: mime_content_type($localPath) ?: 'application/octet-stream',
            extension: pathinfo($originalName, PATHINFO_EXTENSION),
            type: $type,
            orderId: $orderId,
            extra: $extra,
        );
    }

    // ──────────────────────────────────────────────────────────
    // Internal
    // ──────────────────────────────────────────────────────────

    private function uploadRaw(string $content, string $mimeType, string $extension, string $type, int $orderId, ?string $extra): array
    {
        $folderId = $this->folderIds[$type] ?? $this->folderIds['other'];
        $fileName = $this->buildFileName($extension, $type, $orderId, $extra);

        $metadata = new DriveFile([
            'name'    => $fileName,
            'parents' => [$folderId],
        ]);

        $uploaded = $this->driveService->files->create($metadata, [
            'data'       => $content,
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id',
        ]);

        $permission = new Permission();
        $permission->setType('anyone');
        $permission->setRole('reader');
        $this->driveService->permissions->create($uploaded->id, $permission);

        return [
            'url'       => "https://drive.google.com/file/d/{$uploaded->id}/view",
            'file_name' => $fileName,
        ];
    }

    private function buildFileName(string $ext, string $type, int $orderId, ?string $extra): string
    {
        $date = now()->format('Y-m-d');

        return match ($type) {
            'specification'    => "Специфікація_id-{$orderId}_{$date}.{$ext}",
            'supplier_invoice' => "Рахунок_від_постачальника" . ($extra ? "_{$extra}" : '') . "_id-{$orderId}_{$date}.{$ext}",
            'paid_invoice'     => "Оплачений_рахунок" . ($extra ? "_{$extra}" : '') . "_id-{$orderId}_{$date}.{$ext}",
            'commercial'       => "Комерційна_від_постач" . ($extra ? "_#{$extra}" : '') . "_id-{$orderId}_{$date}.{$ext}",
            default            => "Файл_id-{$orderId}_{$date}.{$ext}",
        };
    }
}
