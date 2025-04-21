<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Htmlable; // Needed for label
use Illuminate\Support\Facades\Storage; // If reading headers from file
use App\Services\CsvProcessingService; // Use your CSV service
use Illuminate\Support\Facades\Log;

class PlaceholderButtons extends Field
{
    // Set the view for this component
    protected string $view = 'forms.components.placeholder-buttons';

    // Closure or array to provide the buttons/placeholders
    protected Closure | array $placeholders = [];

    // Method to set the placeholders dynamically
    // Accepts an array ['Label' => 'Placeholder Text', ...] or a Closure
    public function placeholders(Closure | array $placeholders): static
    {
        $this->placeholders = $placeholders;
        return $this;
    }

    // Method to load placeholders based on an uploaded CSV file path
    // Needs the state path of the FileUpload component
    public function fromCsv(string $fileUploadStatePath): static
    {
         // Use a Closure to delay evaluation until form hydration
        $this->placeholders = function (\Filament\Forms\Get $get) use ($fileUploadStatePath) {
            $filePath = $get($fileUploadStatePath); // Get the uploaded file path
            if (!$filePath) {
                return []; // No file uploaded
            }

            $csvService = app(CsvProcessingService::class);
            $headers = [];
            try {
                // Assuming 'private' disk and getHeaders returns an array of header strings
                $rawHeaders = $csvService->getHeaders($filePath, 'private');

                foreach ($rawHeaders as $header) {
                    if (!empty(trim($header))) {
                        // Define label (button text) and placeholder text
                        $label = trim($header);
                        // Define your placeholder syntax here
                        $placeholder = '{{ $' . str_replace(' ', '_', $label) . ' }}';
                        // Or use the safer syntax: "{{ \$__data['{$label}'] }}"
                        // $placeholder = "{{ \$__data['{$label}'] }}";

                         // Use label as key for uniqueness, or generate unique key if needed
                        $headers[$label] = [
                            'label' => $label,
                            'placeholder' => $placeholder,
                        ];
                    }
                }
            } catch (\Exception $e) {
                 Log::error("Error getting headers for PlaceholderButtons: " . $e->getMessage(), ['path' => $filePath]);
                 // Optionally notify the user or return empty array
                 return [];
            }
            return $headers;
        };

        // Make this component reactive to the FileUpload field
        $this->reactive();

        return $this;
    }


    // Override setLabel to allow Htmlable labels if needed
    public function setLabel(string | Htmlable | Closure | null $label): static
    {
         $this->label = $label;
         return $this;
    }

    // Method to retrieve the evaluated placeholders for the view
    public function getPlaceholders(): array
    {
        // Evaluate the closure if it is one, passing form helpers
        $placeholders = $this->evaluate($this->placeholders);

         // Ensure it's an array
         if (!is_array($placeholders)) {
             return [];
         }

         // Convert simple array ['Header1', 'Header2'] to the expected format if needed
         // Or directly expect ['Label' => 'Placeholder', ...] or ['key' => ['label' => 'Label', 'placeholder' => 'Placeholder'], ...]
        if (isset($placeholders[0]) && is_string($placeholders[0])) {
             $formattedPlaceholders = [];
             foreach ($placeholders as $header) {
                 $label = trim($header);
                 $placeholder = '{{ $' . str_replace(' ', '_', $label) . ' }}'; // Adjust syntax
                  // $placeholder = "{{ \$__data['{$label}'] }}";
                 $formattedPlaceholders[$label] = [
                     'label' => $label,
                     'placeholder' => $placeholder,
                 ];
             }
             return $formattedPlaceholders;
         }


        // Ensure the structure is correct ['key' => ['label' => '...', 'placeholder' => '...'], ...]
        $validatedPlaceholders = [];
        foreach($placeholders as $key => $value) {
            if (is_array($value) && isset($value['label']) && isset($value['placeholder'])) {
                $validatedPlaceholders[$key] = $value;
            } elseif (is_string($value)) {
                 // Allow simple ['Label' => 'Placeholder'] format
                 $validatedPlaceholders[$key] = ['label' => $key, 'placeholder' => $value];
            }
        }

        return $validatedPlaceholders;
    }

     // This field doesn't hold a state itself, so make dehydration no-op
     protected function P(): mixed
     {
         return null;
     }

}
