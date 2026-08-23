<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OfferImageSeeder extends Seeder
{
    private const IMAGES_PER_OFFER = 5;

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (DemoCatalog::OFFERS as $offerData) {
            $offer = Offer::query()->where('title', $offerData['title'])->firstOrFail();

            for ($position = 1; $position <= self::IMAGES_PER_OFFER; $position++) {
                $filename = sprintf('%02d.webp', $position);
                $source = database_path("seeders/assets/offers/{$offerData['slug']}/{$filename}");
                $destination = "offers/demo/{$offerData['slug']}/{$filename}";

                if (! File::isFile($source)) {
                    throw new RuntimeException("Photo de démonstration introuvable : {$source}");
                }

                $disk->put($destination, File::get($source));

                OfferImage::updateOrCreate([
                    'offer_id' => $offer->id,
                    'path' => $destination,
                ]);
            }
        }
    }
}
