<?php

namespace App\Services\PublicPosts;

use App\Support\ImageCompressor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A visitor's new post (horse/tool for sale, farrier, transfer), shared by the website's forms and
 * the mobile API: the post's own fields and rules, storing its photos and creating it. Each caller
 * adds how it checks the visitor (the site's captcha, the app's verified phone) and the contact number.
 */
abstract class PublicPostCreator
{
    /**
     * Validation rules for the post's own fields (no contact number, no captcha).
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * The fields' names in validation messages.
     *
     * @return array<string, string>
     */
    abstract public function attributes(): array;

    /**
     * Creates the post and announces it (PublicPostSubmitted: the admins' WhatsApp alert). Active
     * at once (the website), or `pending` until an admin approves it (the app, when reviewed).
     *
     * @param  array<string, mixed>  $data  validated fields
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $files  the uploads by field
     * @param  'active'|'pending'  $status
     */
    abstract public function create(array $data, array $files, string $contactNumber, string $status = 'active'): Model;

    /** Stores an upload on the public disk, compressed like every site image. */
    protected function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        ImageCompressor::compress(Storage::disk('public')->path($path));

        return $path;
    }
}
