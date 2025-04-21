<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\UnavailableStream;
use League\Csv\Exception as CsvException;

class CsvProcessingService
{
    /**
     * Reads the header row from a CSV file stored in Laravel Storage.
     *
     * @param string $filePath Path to the CSV file relative to the storage disk root.
     * @param string $disk Storage disk name (default: 'local').
     * @return array The header row as an array.
     * @throws \Exception If the file cannot be read or is invalid.
     */
    public function getHeaders(string $filePath, string $disk = 'local'): array
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                throw new \Exception("File does not exist: {$filePath} on disk: {$disk}");
            }

            $stream = Storage::disk($disk)->readStream($filePath);
            if ($stream === null) {
                throw new \Exception("Unable to read stream for file: {$filePath} on disk: {$disk}");
            }

            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0); // Assumes header is the first row

            return $csv->getHeader();

        } catch (UnavailableStream | CsvException $e) {
            logger()->error("CSV Header Reading Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
            throw new \Exception("Error reading CSV headers: " . $e->getMessage());
        } catch (\Throwable $e) {
            logger()->error("General CSV Header Reading Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
            throw new \Exception("An unexpected error occurred while reading CSV headers.");
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Returns an iterator to efficiently read records (rows) from a CSV file.
     * Skips the header row.
     *
     * @param string $filePath Path to the CSV file relative to the storage disk root.
     * @param string $disk Storage disk name (default: 'local').
     * @return array An array of each data row as an associative array (header => value).
     * @throws \Exception If the file cannot be read or is invalid.
     */
    public function getRecords(string $filePath, string $disk = 'local'): array
    {
        try {
            $stream = Storage::disk($disk)->readStream($filePath);
            if ($stream === null) {
                throw new \Exception("Unable to read stream for file: {$filePath} on disk: {$disk}");
            }

            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0); // Assumes header is the first row

            $stmt = new Statement(); // No offset or limit needed here for iterator

            $records = $stmt->process($csv); // Returns an iterator

            return iterator_to_array($records, true); // Convert iterator to array

        } catch (UnavailableStream | CsvException $e) {
            logger()->error("CSV Record Reading Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
            throw new \Exception("Error reading CSV records: " . $e->getMessage());
        } catch (\Throwable $e) {
            logger()->error("General CSV Record Reading Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
            throw new \Exception("An unexpected error occurred while preparing CSV records.");
        }
    }

     /**
     * Counts the total number of data rows (excluding the header) in a CSV file.
     *
     * @param string $filePath Path to the CSV file relative to the storage disk root.
     * @param string $disk Storage disk name (default: 'local').
     * @return int The number of data rows.
     * @throws \Exception If the file cannot be read or is invalid.
     */
    public function getTotalRows(string $filePath, string $disk = 'local'): int
    {
        try {
            $stream = Storage::disk($disk)->readStream($filePath);
            if ($stream === null) {
                 throw new \Exception("Unable to read stream for file: {$filePath} on disk: {$disk}");
            }

            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0); // Important for count to exclude header if needed

            // Count records *after* setting header offset if you want data rows only
            return count($csv);

        } catch (UnavailableStream | CsvException $e) {
             logger()->error("CSV Row Counting Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
            throw new \Exception("Error counting CSV rows: " . $e->getMessage());
        } catch (\Throwable $e) {
             logger()->error("General CSV Row Counting Error: " . $e->getMessage(), ['path' => $filePath, 'disk' => $disk]);
             throw new \Exception("An unexpected error occurred while counting CSV rows.");
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
