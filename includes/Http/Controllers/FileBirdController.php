<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Services\FileBird;

if (!defined('ABSPATH')) {
    exit;
}


class FileBirdController extends Controller
{
    public function getFolders(): \WP_REST_Response
    {
        return $this->response([
            'success' => true,
            'folders' => FileBird::getFolders(),
        ]);
    }

    public function getAttachments(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $folderId = isset($params['folder_id']) ? absint($params['folder_id']) : 0;

        if (!$folderId) {
            return $this->response([
                'success' => false,
                'message' => __('Folder ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        // Guard against IDOR: verify the folder exists and the current user may access it.
        // Global folders (created_by = 0) are accessible to all authenticated users.
        // User-specific folders (created_by = N) are restricted to their owner and admins.
        if (!current_user_can('manage_options')) {
            $folderOwner = FileBird::getFolderOwner($folderId);
            if ($folderOwner === null) {
                return $this->response([
                    'success' => false,
                    'message' => __('Folder not found', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ], 404);
            }
            if ($folderOwner !== 0 && $folderOwner !== get_current_user_id()) {
                return $this->response([
                    'success' => false,
                    'message' => __('Not authorized to access this folder', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ], 403);
            }
        }

        $attachmentIds = FileBird::getAttachmentIdsByFolder($folderId);

        return $this->response([
            'success' => true,
            'attachments' => $attachmentIds,
            'count' => count($attachmentIds),
        ]);
    }
}
