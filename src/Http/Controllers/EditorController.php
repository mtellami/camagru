<?php

declare(strict_types=1);

namespace Camagru\Http\Controllers;

use Camagru\Http\Http;
use Camagru\Security\Auth;
use Camagru\Media\Images;
use Camagru\Media\PhotoPublisher;
use Camagru\Repositories\PhotoRepository;

final class EditorController
{
    /** Render the editor with the signed-in user's creation history. */
    public function show(): never
    {
        $user = Auth::requireUser();
        $result = (new PhotoRepository())->gallery((int) $user['id']);
        Http::render('editor', ['title' => 'Make a moment', 'history' => $result]);
    }

    /** Return the server-approved sticker catalog. */
    public function overlays(): never
    {
        Http::json(['ok' => true, 'data' => Images::OVERLAYS]);
    }

    /** Validate a capture request and publish its photo or GIF. */
    public function publish(bool $gif = false): never
    {
        $user = Auth::requireUser();
        Auth::rate('capture', 30, 3600, (string) $user['id']);
        $overlays = Images::overlays($_POST['overlay'] ?? []);
        $submission = Http::input('submission');
        session_write_close();
        $photo = PhotoPublisher::publish(
            (int) $user['id'],
            $overlays,
            $submission,
            $gif,
        );
        if (Http::jsonRequested()) {
            Http::json(
                [
                    'ok' => true,
                    'data' => [
                        'message' => 'Your moment is in the gallery.',
                        'redirect' => '/photos/' . $photo['id'],
                        'photo' => $photo,
                    ],
                ],
                201,
            );
        }
        Http::redirect('/photos/' . $photo['id']);
    }
}
