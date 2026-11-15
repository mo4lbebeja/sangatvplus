<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\GalleryPhoto;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'gallery' => GalleryPhoto::query()->visible()->get()->map(fn (GalleryPhoto $p) => [
                'id' => $p->id,
                'album' => $p->album,
                'title' => $p->title,
                'caption' => $p->caption,
                'image_url' => MediaUrl::of($p->image_path),
                'thumb_url' => MediaUrl::of($p->thumb_path ?: $p->image_path),
            ]),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (Facility $f) => [
                    'id' => $f->id,
                    'category' => $f->category->value,
                    'name' => $f->name,
                    'description' => $f->description,
                    'location' => $f->location,
                    'hours' => $f->hours,
                    'image_url' => MediaUrl::of($f->image_path),
                ]),
        ]);
    }
}
