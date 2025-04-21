<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGeneratorService
{
    protected EmailTemplateService $templateService;

    public function __construct(EmailTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Generates a personalized PDF from an HTML template and data.
     * Saves the PDF to storage and returns the path.
     *
     * @param string $htmlTemplate The HTML template string with placeholders.
     * @param array $data Associative array of data for placeholder replacement.
     * @param int $batchId The ID of the email batch (for file naming).
     * @param string $recipientIdentifier Unique identifier for the recipient (for file naming).
     * @return string The storage path to the generated PDF file.
     * @throws \Exception If PDF generation or saving fails.
     */
    public function generatePdfFromTemplate(string $htmlTemplate, array $data, int $batchId, string $recipientIdentifier): string
    {
        // 1. Replace placeholders in the HTML template
        $processedHtml = $this->templateService->replacePlaceholders($htmlTemplate, $data);

        // 2. Generate PDF using laravel-dompdf
        $pdf = Pdf::loadHTML($processedHtml);
        // You can set options like paper size, orientation etc.
        // $pdf->setPaper('a4', 'portrait');

        // 3. Define a unique path and filename
        // Ensure recipientIdentifier is filesystem-safe
        $safeRecipientId = Str::slug($recipientIdentifier);
        $filename = 'personalized_attachment_' . $batchId . '_' . $safeRecipientId . '_' . time() . '.pdf';
        // Store in a dedicated directory, e.g., 'personalized_attachments/{batch_id}/'
        $directory = 'personalized_attachments/' . $batchId;
        $filePath = $directory . '/' . $filename;

        // 4. Save the PDF to storage (e.g., local disk)
        // Ensure the directory exists
        Storage::disk('local')->makeDirectory($directory); // Or your chosen disk

        if (!Storage::disk('local')->put($filePath, $pdf->output())) {
             throw new \Exception("Failed to save generated PDF to storage at path: {$filePath}");
        }

        // 5. Return the storage path (relative to the storage disk root)
        return $filePath;
    }
}
