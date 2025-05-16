<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Request $request)
    {
        $path = $request->query('path');
        abort_unless(Storage::disk('private')->exists($path), 404);
        return Storage::disk('private')->download($path);
    }
}
