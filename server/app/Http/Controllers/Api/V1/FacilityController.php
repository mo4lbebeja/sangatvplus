<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
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
