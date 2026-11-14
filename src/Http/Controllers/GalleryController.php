<?php

declare(strict_types=1);

namespace Camagru\Http\Controllers;

use Camagru\Http\Http;
use Camagru\Security\Auth;
use Camagru\Repositories\PhotoRepository;

final class GalleryController
{
    /** Render the gallery or return the next batch as JSON. */
    public function show(bool $personal = false): never
    {
        $owner = $personal ? (int) Auth::requireUser()['id'] : null;
        $result = (new PhotoRepository())->gallery($owner);
        if (Http::jsonRequested()) {
            Http::json(['ok' => true, 'data' => $result]);
        }
        Http::render('gallery', array_merge(['title' => 'The gallery'], $result));
    }
}
