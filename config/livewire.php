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
        // This is a SINGLE global ceiling shared by every upload field in the
        // app, including the unauthenticated /search/visual endpoint — 30720
        // (video) is the largest legitimate need, so this cannot drop to a
        // small "images only" number (e.g. 8192) without breaking seller
        // product videos and admin banner videos; 31744 trims the previous
        // 2MB of unused headroom down to 1MB above the video field limit
        // (AL-L6, security audit).
        'rules' => ['required', 'file', 'max:31744'],

        // Package default list + webm so video previews work in forms.
        // 'svg' removed (AL-C17, security audit): Livewire serves temp-upload
        // previews inline, same-origin, so an SVG preview is same-origin
        // script execution — no field in this app needs an SVG preview.
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma', 'webm',
        ],
    ],

];
