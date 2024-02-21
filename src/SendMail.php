<?php

namespace OriceOn\MailTracker;

use Illuminate\Support\Facades\Mail;
use DOMDocument;
use DOMXpath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendMail
{
    public function __construct(array $mailData) {
        $mailData['message'] = $this->convert_inline_images($mailData['message']);

        Mail::send(new MailTrackerSend($mailData));
    }

    protected function convert_inline_images(string $message): string
    {
        if (config('mail-tracker.convert-inline-images')) {
            $dom = new DOMDocument('1.0', 'UTF-8');

            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8'));
            $dom->encoding = 'UTF-8';
            $dom->preserveWhiteSpace = false;
            libxml_clear_errors();

            $signature = $dom->getElementByID(config('mail-tracker.exclude-inline-images-signature') ?: 'mail-tracker-signature');

            $images = $dom->getElementsByTagName('img');

            $totalImages = count($images);

            if ($totalImages > 0) {
                $path = config('mail-tracker.inline-images-filesystem-folder') . '/' . Str::uuid();

                foreach ($images as $image) {
                    if ( ! $signature?->contains($image)) {
                        $src = $image->getAttribute('src');

                        if (str_starts_with($src, 'data:image/')) {
                            $base64Str = substr($src, strpos($src, ',') + 1);
                            $mimeType = trim(substr(explode(';base64,', $src)[0], 5));

                            $ext = match ($mimeType) {
                                'image/png'  => 'png',
                                'image/jpg',
                                'image/jpeg' => 'jpg',
                                'image/gif'  => 'gif',
                                'image/bmp'  => 'bmp',
                                'image/webp' => 'webp',
                                default => 'jpg',
                            };

                            $name = md5(Str::random(30) . time()) . '.' . $ext;

                            Storage::disk(config('mail-tracker.inline-images-filesystem') ?: 'public')->put($path . '/' . $name, base64_decode($base64Str));
                            $image->setAttribute('src', config('app.url') . '/storage/' . $path . '/' . $name);
                        }
                    }
                }

                $message = $dom->saveHTML($dom->documentElement);
            }
        }

        return $message;
    }
}
