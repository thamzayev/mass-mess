<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade; // Use an alias to avoid conflicts if needed
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable; // Import Throwable for better error handling

class PdfGeneratorService
{
    protected EmailTemplateService $templateService;

    public function __construct(EmailTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Generates the full HTML content for the PDF, including header, body, and footer,
     * after replacing placeholders.
     *
     * @param string $bodyTemplate The main HTML template string for the body.
     * @param array $data Associative array of data for placeholder replacement.
     * @param string $headerTemplate Optional HTML template for the header.
     * @param string $footerTemplate Optional HTML template for the footer.
     * @return string The fully rendered HTML content.
     * @throws \Exception If view rendering fails.
     */
    public function generateHtmlContent(
        string $bodyTemplate,
        array $data,
        string $headerTemplate = '',
        string $footerTemplate = ''
    ): string {
        try {
            $bodyHtml = $this->templateService->replacePlaceholders($bodyTemplate, $data);
            $headerHtml = $headerTemplate ? $this->templateService->replacePlaceholders($headerTemplate, $data) : '';
            $footerHtml = $footerTemplate ? $this->templateService->replacePlaceholders($footerTemplate, $data) : '';

            // Render full HTML layout using the pdf.layout view
            // Ensure 'pdf.layout' view exists and accepts these variables
            return view('pdf.layout', [
                'bodyHtml' => $bodyHtml,
                'headerHtml' => $headerHtml,
                'footerHtml' => $footerHtml,
            ])->render();
        } catch (Throwable $e) {
            // Log the error for debugging
            logger()->error("PDF HTML content generation failed: " . $e->getMessage(), [
                'exception' => $e,
                'data_keys' => array_keys($data) // Avoid logging potentially sensitive data values
            ]);
            throw new \Exception("Failed to generate PDF HTML content: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generates raw PDF content from HTML.
     *
     * @param string $htmlContent The HTML content to convert.
     * @param string $paper Paper size (e.g., 'a4', 'letter').
     * @param string $orientation Paper orientation ('portrait' or 'landscape').
     * @return string The raw PDF content (binary string).
     * @throws \Exception If PDF generation fails.
     */
    public function generatePdfContent(
        string $htmlContent,
        string $paper = 'a4',
        string $orientation = 'portrait'
    ): string {
        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isFontSubsettingEnabled', true); // Enable font subsetting for embedding

            // Temporary for development server
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false, // Set to true in production if possible
                    'verify_peer_name' => false, // Set to true in production if possible
                    'allow_self_signed' => true, // Typically for development/trusted self-signed certs
                ]
            ]);

            // $pdf = DomPdfFacade::loadHTML($htmlContent);
            // $pdf->setPaper($paper, $orientation);
            // return $pdf->output();

            $htmlContent = $this->centerWrappedImages($htmlContent); // Center images in the HTML
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setHttpContext($context);
            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();
            return $dompdf->output();
        } catch (\Dompdf\Exception\ImageException $e) {
            logger()->error("DomPDF Image Loading Error: " . $e->getMessage(), ['exception' => $e]);
            throw new \Exception("Failed to load an image for PDF generation: " . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            // Log the error
            logger()->error("DomPDF generation failed: " . $e->getMessage(), ['exception' => $e]);
            throw new \Exception("Failed to generate PDF content using DomPDF: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generates a standardized filename for a personalized attachment.
     *
     * @param int $batchId The ID of the email batch.
     * @param string $recipientIdentifier Unique identifier for the recipient.
     * @param string $originalFilename Optional user-provided filename template (placeholders not processed here).
     * @return string The generated filename.
     */
    public function generateFilename(int $batchId, string $recipientIdentifier, string $originalFilename = ''): string
    {
        $safeRecipientId = Str::slug($recipientIdentifier);
        $timestamp = time();

        if ($originalFilename && pathinfo($originalFilename, PATHINFO_EXTENSION) === 'pdf') {
             // Use user filename structure if provided and valid, but ensure uniqueness
             $baseName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
             return "{$baseName}_{$batchId}_{$safeRecipientId}_{$timestamp}.pdf";
        }

        // Default filename structure
        return "personalized_attachment_{$batchId}_{$safeRecipientId}_{$timestamp}.pdf";
    }

    /**
     * Saves the raw PDF content to the specified storage disk and path.
     * Creates the directory if it doesn't exist.
     *
     * @param string $pdfContent Raw PDF binary string.
     * @param string $directory The directory path relative to the disk root.
     * @param string $filename The name of the file.
     * @param string $disk The storage disk name (default: 'private').
     * @return string The full storage path (directory/filename).
     * @throws \Exception If saving fails.
     */
    public function savePdf(string $pdfContent, string $directory, string $filename, string $disk = 'private'): string
    {
        $filePath = $directory . '/' . $filename;

        try {
            // Ensure the directory exists
            Storage::disk($disk)->makeDirectory($directory);

            // Attempt to save the file
            if (!Storage::disk($disk)->put($filePath, $pdfContent)) {
                throw new \Exception("Storage::put returned false.");
            }

            return $filePath;
        } catch (Throwable $e) {
            logger()->error("Failed to save generated PDF to storage", [
                'disk' => $disk,
                'path' => $filePath,
                'exception' => $e,
            ]);
            throw new \Exception("Failed to save generated PDF to storage at path: {$filePath} on disk: {$disk}. Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Orchestrates the generation and saving of a personalized PDF.
     * (This replaces the original single method)
     *
     * @param string $bodyTemplate The HTML template string for the body.
     * @param array $data Associative array of data for placeholder replacement.
     * @param int $batchId The ID of the email batch.
     * @param string $recipientIdentifier Unique identifier for the recipient.
     * @param string $headerTemplate Optional HTML template for the header.
     * @param string $footerTemplate Optional HTML template for the footer.
     * @param string $filenameTemplate Optional user-defined filename structure.
     * @param string $disk Storage disk name (default: 'private').
     * @return string The storage path to the generated PDF file.
     * @throws \Exception If any step in the process fails.
     */
    public function generateAndSavePdf(
        string $bodyTemplate,
        array $data,
        int $batchId,
        string $recipientIdentifier,
        string $headerTemplate = '',
        string $footerTemplate = '',
        string $filenameTemplate = '', // Added for user-defined names
        string $disk = 'private'
    ): string {
        // 1. Generate HTML content
        $htmlContent = $this->generateHtmlContent($bodyTemplate, $data, $headerTemplate, $footerTemplate);
        // 2. Generate PDF raw content
        $pdfContent = $this->generatePdfContent($htmlContent); // Uses default 'a4', 'portrait'

        // 3. Generate Filename
        // Note: filenameTemplate placeholders are NOT replaced here.
        // If you need placeholder replacement in the filename, that logic
        // should happen *before* calling this method, likely where you retrieve
        // the filename template from the PersonalizedAttachment model/form state.
        $filename = $this->generateFilename($batchId, $recipientIdentifier, $filenameTemplate);

        // 4. Define directory and Save PDF
        $directory = 'personalized_attachments/' . $batchId;
        $filePath = $this->savePdf($pdfContent, $directory, $filename, $disk);

        return $filePath;
    }

    /**
     * Alias for the main generation method for backward compatibility or clearer naming.
     *
     * @deprecated Use generateAndSavePdf instead for clarity.
     */
    public function generatePdfFromTemplate(...$args): string
    {
        return $this->generateAndSavePdf(...$args);
    }

    function centerWrappedImages($htmlContent) {
        return preg_replace_callback(
            '#<p([^>]*)>\s*(<img[^>]*style="display:\s*block;\s*margin-left:\s*auto;\s*margin-right:\s*auto;"[^>]*>)\s*</p>#i',
            function ($matches) {
                $pTagAttrs = $matches[1];
                $imgTag = $matches[2];

                // Check if style already includes text-align
                if (preg_match('/style\s*=\s*"(.*?)"/i', $pTagAttrs, $styleMatch)) {
                    $style = $styleMatch[1];
                    if (stripos($style, 'text-align') === false) {
                        $newStyle = rtrim($style, ';') . '; text-align: center;';
                        $pTagAttrs = preg_replace('/style\s*=\s*".*?"/i', 'style="' . $newStyle . '"', $pTagAttrs);
                    }
                } else {
                    $pTagAttrs .= ' style="text-align: center;"';
                }

                return "<p{$pTagAttrs}>{$imgTag}</p>";
            },
            $htmlContent
        );
    }

}
