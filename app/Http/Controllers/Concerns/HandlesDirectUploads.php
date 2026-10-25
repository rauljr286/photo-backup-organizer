<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\DirectUploadException;
use App\Http\Requests\CompleteDirectUploadRequest;
use App\Http\Requests\PrepareDirectUploadRequest;
use App\Services\DirectUploadService;
use App\Support\DirectUpload;
use App\Support\PhotoUploadResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The endpoints of the direct (presigned) upload.
 *
 * "prepare" hands out a signed URL for one file, "complete" registers the
 * file that was uploaded straight to the bucket as a photo. The result is
 * reported exactly like the classic upload flow reports it, so the browser
 * renders both the same way.
 */
trait HandlesDirectUploads
{
    /**
     * Hand out a signed URL for one photo.
     */
    public function prepareUpload(PrepareDirectUploadRequest $request): JsonResponse
    {
        $this->guardDirectUpload();

        $private = (bool) $request->input('private', false);

        if ($private) {
            if (! $this->allowsPrivateUploads()) {
                abort(403, 'The private gallery is only available in the browser.');
            }

            $this->guardPrivateGallery($request);
        }

        try {
            $prepared = app(DirectUploadService::class)->prepare(
                $request->user(),
                (string) $request->input('filename'),
                (int) $request->input('size'),
                $request->input('mime_type'),
                $request->input('album_id') !== null ? (int) $request->input('album_id') : null,
                $request->input('alt_text'),
                $request->input('tags'),
                $private,
            );
        } catch (DirectUploadException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ], $e->status());
        }

        return response()->json($prepared, 201);
    }

    /**
     * Register the photo that was uploaded straight to the bucket.
     */
    public function completeUpload(CompleteDirectUploadRequest $request): JsonResponse
    {
        $this->guardDirectUpload();

        try {
            $result = app(DirectUploadService::class)->complete(
                $request->user(),
                (string) $request->input('upload_id'),
            );
        } catch (DirectUploadException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ], $e->status());
        }

        $uploaded = collect($result['uploaded'])
            ->map(fn (array $entry) => PhotoUploadResponse::photoPayload($entry['photo'], $entry['key']))
            ->values()
            ->all();

        return response()->json(
            PhotoUploadResponse::make($uploaded, $result['failed'], $result['duplicates']),
            $uploaded !== [] ? 201 : 200,
        );
    }

    /**
     * The endpoints only exist when the photos are uploaded straight to the
     * bucket.
     */
    protected function guardDirectUpload(): void
    {
        if (! DirectUpload::isEnabled()) {
            abort(404, 'Direct uploads are not enabled.');
        }
    }

    /**
     * Whether photos may go into the private gallery. The API has no session,
     * so the private gallery stays a browser-only feature.
     */
    protected function allowsPrivateUploads(): bool
    {
        return false;
    }

    /**
     * Make sure the private gallery is unlocked before a photo is uploaded
     * into it.
     */
    protected function guardPrivateGallery(Request $request): void
    {
        abort(403, 'The private gallery is locked.');
    }
}
