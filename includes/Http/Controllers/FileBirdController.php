<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Services\FileBird;

if (!defined('ABSPATH')) {
    exit;
}


class FileBirdController extends Controller
{
    public function getFolders()
    {
        return $this->response([
            'success' => true,
            'folders' => FileBird::getFolders(),
        ]);
    }

    public function getAttachments($request)
    {
        $params = $request->get_json_params();
        $folderId = (int) ($params['folder_id'] ?? 0);

        if (!$folderId) {
            return $this->response([
                'success' => false,
                'message' => __('Folder ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $attachmentIds = FileBird::getAttachmentIdsByFolder($folderId);

        return $this->response([
            'success' => true,
            'attachments' => $attachmentIds,
            'count' => count($attachmentIds),
        ]);
    }
}
