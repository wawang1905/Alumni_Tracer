<?php

namespace App\Models;

use CodeIgniter\Model;

class AlumniFilesModel extends Model
{
    protected $table         = 'alumni_files';
    protected $primaryKey    = 'File_ID';
    protected $returnType    = 'array';
    protected $useTimestamps = false; // table uses its own Uploaded_At timestamp

    protected $allowedFields = [
        'Alumni_ID',
        'File_Type',
        'Original_Name',
        'Stored_Name',
        'File_Path',
        'Mime_Type',
        'File_Size',
    ];

    /**
     * Returns the most-recently uploaded profile picture row for an alumni,
     * or null if none exists.
     */
    public function getActiveProfilePicture(int $alumniId): ?array
    {
        $row = $this->builder()
            ->where('Alumni_ID', $alumniId)
            ->where('File_Type', 'profile_picture')
            ->orderBy('Uploaded_At', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }
}