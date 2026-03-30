<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Services\FileRenamer;

if (!defined('ABSPATH')) {
    exit;
}

class FileRenamerController extends Controller
{
    protected FileRenamer $renamer;

    public function __construct()
    {
        $this->renamer = new FileRenamer();
    }

    public function rename($request)
    {
        $params = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);
        $customFilename = sanitize_text_field($params['filename'] ?? '');

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $result = $this->renamer->rename($attachmentId, $customFilename);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        if (!empty($result['already_named'])) {
            return $this->response([
                'success' => true,
                'already_named' => true,
                'message' => __('File already has the correct name', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'old_filename' => $result['old_filename'],
                'new_filename' => $result['new_filename']
            ]);
        }

        return $this->response([
            'success' => true,
            'message' => __('File renamed successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'old_filename' => $result['old_filename'],
            'new_filename' => $result['new_filename']
        ]);
    }

    public function renameBulk($request)
    {
        $params = $request->get_json_params();
        $attachmentIds = array_map('absint', $params['attachment_ids'] ?? []);

        if (empty($attachmentIds)) {
            return $this->response([
                'success' => false,
                'message' => __('No attachments provided', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        if (count($attachmentIds) > 50) {
            return $this->response([
                'success' => false,
                'message' => __('Maximum 50 images can be renamed at once', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($attachmentIds as $id) {
            $result = $this->renamer->rename($id);
            $results[$id] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return $this->response([
            'success' => $errorCount === 0,
            /* translators: %1$d: number of successfully renamed files, %2$d: total number of files */
            'message' => sprintf(__('Renamed %1$d/%2$d files', 'imagecraft-ai-alt-text-file-renamer-image-seo'), $successCount, count($attachmentIds)),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }

    public function preview($request)
    {
        $params = $request->get_params();
        $attachmentId = (int) ($params['attachment_id'] ?? $params['id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $result = $this->renamer->previewRename($attachmentId);

        return $this->response($result);
    }
}
