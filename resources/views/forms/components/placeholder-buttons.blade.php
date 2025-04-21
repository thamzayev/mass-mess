<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :label-sr-only="$isLabelHidden()"
>
    {{-- Evaluate placeholders --}}
    @php
        $placeholders = $getPlaceholders();
        $statePath = $getStatePath(); // Unique ID for this component instance
    @endphp

    {{-- Display label if not hidden --}}
    @if ($getLabel() && !$isLabelHidden())
        <label for="{{ $getId() }}" class="inline-block text-sm font-medium leading-6 text-gray-950 dark:text-white">
            {{ $getLabel() }}
        </label>
    @endif

    <div
        x-data="{}" {{-- Basic Alpine data scope if needed later --}}
        id="{{ $getId() }}"
        class="mt-2 p-2 border border-gray-300 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-800/50"
        {{ $attributes->merge($getExtraAttributes())->class([]) }}
    >
        @if (!empty($placeholders))
            <div class="flex flex-wrap gap-2" x-ref="buttonContainer_{{ $statePath }}">
                @foreach ($placeholders as $key => $details)
                    <button
                        type="button"
                        draggable="true"
                        x-on:dragstart="
                            event.dataTransfer.setData('text/plain', '{{ addslashes($details['placeholder']) }}');
                            event.dataTransfer.effectAllowed = 'copy';
                            console.log('Dragging placeholder:', '{{ addslashes($details['placeholder']) }}');
                        "
                        class="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-800 cursor-move"
                    >
                        {{ $details['label'] }}
                    </button>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No placeholders available. Please upload a CSV file first.
            </p>
        @endif
    </div>

    {{-- Basic JS to listen for drops on standard text inputs --}}
    {{-- !!! IMPORTANT: This WILL NOT work reliably for RichTextEditor (Tiptap) !!! --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Example: Target a specific TextInput by its ID
            const targetTextInput = document.getElementById('data.email_title_template'); // Adjust ID
            if (targetTextInput) {
                 targetTextInput.addEventListener('dragover', (event) => {
                    event.preventDefault(); // Necessary to allow dropping
                    event.dataTransfer.dropEffect = 'copy';
                });

                targetTextInput.addEventListener('drop', (event) => {
                    event.preventDefault();
                    const placeholder = event.dataTransfer.getData('text/plain');
                    if (placeholder) {
                         // Insert placeholder at cursor position (basic example)
                        const start = targetTextInput.selectionStart;
                        const end = targetTextInput.selectionEnd;
                        targetTextInput.value = targetTextInput.value.substring(0, start) + placeholder + targetTextInput.value.substring(end);
                        targetTextInput.selectionStart = targetTextInput.selectionEnd = start + placeholder.length;
                         // Trigger input event for Livewire/Alpine to pick up changes
                        targetTextInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }

            // --- Targeting RichTextEditor (Tiptap) ---
            // This requires accessing the Tiptap editor instance associated with the Filament field.
            // You might need:
            // 1. Custom JS hooks provided by Filament's RichEditor.
            // 2. Accessing the Tiptap instance via window or specific events.
            // 3. Using Tiptap's commands (e.g., `editor.chain().focus().insertContent(placeholder).run()`)
            //    to insert the placeholder at the correct position within the editor state.
            // This requires significantly more complex JS integration.
            // Example conceptual structure (needs actual implementation):
            /*
            const richEditorElement = document.querySelector('[tiptap-instance-for="data.email_body_template"]'); // Fictional selector
            if (richEditorElement && richEditorElement._tiptapEditor) { // Fictional property
                const tiptapEditor = richEditorElement._tiptapEditor;

                richEditorElement.addEventListener('dragover', (event) => { // Attach to the Tiptap element
                     event.preventDefault();
                     event.dataTransfer.dropEffect = 'copy';
                });

                richEditorElement.addEventListener('drop', (event) => {
                     event.preventDefault();
                     const placeholder = event.dataTransfer.getData('text/plain');
                     if (placeholder && tiptapEditor) {
                         tiptapEditor.chain().focus().insertContentAt(tiptapEditor.state.selection.anchor, placeholder).run();
                     }
                });
            }
            */
        });
    </script>

</x-dynamic-component>
