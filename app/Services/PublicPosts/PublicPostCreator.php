<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Support\ImageCompressor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

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

    /** The price columns are decimal(10, 2): a bigger price is refused, not a database error. */
    public const MAX_PRICE = 99999999;

    /** @var list<string> uploads stored for the post being created */
    private array $stored = [];

    /**
     * Creates the post and announces it (PublicPostSubmitted: the admins' WhatsApp alert). Active
     * at once (the website), or `pending` until an admin approves it (the app, when reviewed). When
     * the post cannot be saved, the uploads already stored for it are deleted again.
     *
     * @param  array<string, mixed>  $data  validated fields
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $files  the uploads by field
     * @param  'active'|'pending'  $status
     */
    final public function create(array $data, array $files, string $contactNumber, string $status = 'active'): Model
    {
        $this->stored = [];

        try {
            $post = $this->make($data, $files, $contactNumber, $status);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($this->stored);

            throw $e;
        } finally {
            $this->stored = [];
        }

        PublicPostSubmitted::dispatch($post);

        return $post;
    }

    /**
     * Stores the uploads (with store()) and creates the post.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $files
     * @param  'active'|'pending'  $status
     */
    abstract protected function make(array $data, array $files, string $contactNumber, string $status): Model;

    /** Stores an upload on the public disk, compressed like every site image. */
    protected function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        $this->stored[] = $path;

        ImageCompressor::compress(Storage::disk('public')->path($path));

        return $path;
    }
}
