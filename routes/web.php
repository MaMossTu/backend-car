<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function () {
    return view('welcome');
});

// แทน public/storage symlink (FTP / shared hosting ที่รัน storage:link ไม่ได้)
Route::get('/storage/{path}', function (string $path): BinaryFileResponse {
    if (str_contains($path, '..')) {
        abort(404);
    }
    $full = storage_path('app/public/' . $path);
    if (!is_file($full)) {
        abort(404);
    }

    return response()->file($full);
})->where('path', '.*');
