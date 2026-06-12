<?php

/*
 * Minimal Livewire config override — every other key falls back to the
 * package defaults (LivewireServiceProvider merges this file shallowly and
 * each read in FileUploadConfiguration has its own fallback).
 */
return [

    'temporary_file_upload' => [
        // Raised from Livewire's 12MB default so product/banner videos
        // (≤30MB, validated per-field with mimetypes+max:30720) can upload.
        // Sits ABOVE the field limit so oversized files reach the field
        // validator and produce a proper inline error instead of vanishing.
        'rules' => ['required', 'file', 'max:32768'],

        // Package default list + webm so video previews work in forms.
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma', 'webm',
        ],
    ],

];
