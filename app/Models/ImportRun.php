<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Points to catalog_sync_runs — the table written by the hourly netbazar:catalog-import command.
// The legacy import_runs table (2 rows from the one-time SQLite migration) is kept but no longer displayed.
class ImportRun extends Model
{
    protected $table      = 'catalog_sync_runs';
    public    $timestamps = false;
}
