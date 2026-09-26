<?php
declare(strict_types=1);

namespace App\Models;

class MediaFile
{
    public int $id;
    public string $related_type;
    public int $related_id;
    public string $file_url;
    public string $file_type;
    public ?int $file_size_kb = null;
    public ?string $original_name = null;
    public int $uploaded_by;
    public string $created_at;

    public static function tableName(): string
    {
        return 'media_files';
    }
}
