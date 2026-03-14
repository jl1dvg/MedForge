<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenNasIndex extends Model
{
    protected $table = 'imagenes_nas_index';

    protected $fillable = [
        'form_id',
        'hc_number',
        'has_files',
        'files_count',
        'image_count',
        'pdf_count',
        'total_bytes',
        'latest_file_mtime',
        'sample_file',
        'scan_status',
        'last_error',
        'scan_duration_ms',
        'last_scanned_at',
    ];

    protected $casts = [
        'has_files' => 'boolean',
        'files_count' => 'integer',
        'image_count' => 'integer',
        'pdf_count' => 'integer',
        'total_bytes' => 'integer',
        'latest_file_mtime' => 'datetime',
        'scan_duration_ms' => 'integer',
        'last_scanned_at' => 'datetime',
    ];
}
