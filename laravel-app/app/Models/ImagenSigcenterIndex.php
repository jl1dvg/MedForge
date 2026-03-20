<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenSigcenterIndex extends Model
{
    protected $table = 'imagenes_sigcenter_index';

    protected $fillable = [
        'form_id',
        'hc_number',
        'pedido_cirugia_id',
        'derivacion_pedido_id',
        'doc_solicitud_id',
        'has_files',
        'has_db_rows',
        'files_count',
        'image_count',
        'pdf_count',
        'verified_files_count',
        'total_bytes',
        'latest_file_mtime',
        'sample_file',
        'scan_status',
        'last_error',
        'scan_duration_ms',
        'candidate_doc_ids',
        'files_meta',
        'last_scanned_at',
    ];

    protected $casts = [
        'has_files' => 'boolean',
        'has_db_rows' => 'boolean',
        'files_count' => 'integer',
        'image_count' => 'integer',
        'pdf_count' => 'integer',
        'verified_files_count' => 'integer',
        'total_bytes' => 'integer',
        'latest_file_mtime' => 'datetime',
        'scan_duration_ms' => 'integer',
        'candidate_doc_ids' => 'array',
        'files_meta' => 'array',
        'last_scanned_at' => 'datetime',
    ];
}
