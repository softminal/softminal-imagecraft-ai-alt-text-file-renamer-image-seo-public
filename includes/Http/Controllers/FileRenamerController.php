<?php

namespace ImageCraft\Includes\Http\Controllers;

use ImageCraft\Includes\Services\FileRenamer;

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
        $customFilename = $params['filename'] ?? '';

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => 'Attachment ID is required'
            ], 400);
        }

        $result = $this->renamer->rename($attachmentId, $customFilename);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        return $this->response([
            'success' => true,
            'message' => 'File renamed successfully',
            'old_filename' => $result['old_filename'],
            'new_filename' => $result['new_filename']
        ]);
    }

    public function renameBulk($request)
    {
        $params = $request->get_json_params();
        $attachmentIds = array_map('intval', $params['attachment_ids'] ?? []);

        if (empty($attachmentIds)) {
            return $this->response([
                'success' => false,
                'message' => 'No attachments provided'
            ], 400);
        }

        if (count($attachmentIds) > 50) {
            return $this->response([
                'success' => false,
                'message' => 'Maximum 50 images can be renamed at once'
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
            'message' => sprintf('Renamed %d/%d files', $successCount, count($attachmentIds)),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }

    public function preview($request)
    {
        $attachmentId = (int) ($request['id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => 'Attachment ID is required'
            ], 400);
        }

        $result = $this->renamer->previewRename($attachmentId);

        return $this->response($result);
    }
}
