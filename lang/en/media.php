<?php

declare(strict_types=1);

return [

    /*
     * HEIC is refused — but the refusal now tells the truth and hands the
     * owner a way out.
     *
     * The container is the same one MP4 uses (`ftyp`), so an iPhone
     * photograph used to be answered with "this product does not accept
     * video". That sentence was wrong twice over: the file is a
     * photograph, and the reader was left with nothing to do about it.
     *
     * What did NOT change: HEIC is still rejected. There is no proven
     * HEIC decoder in this product, and accepting a file we cannot decode
     * would store an unopenable row with no renditions. When a decoder is
     * proven with a real fixture, this string is the first thing to go.
     */
    'heic_needs_jpeg' => 'HEIC photos cannot be published yet: this product has no HEIC decoder. Convert the photo to JPEG and upload the JPEG — on iPhone, Settings › Camera › Formats › Most Compatible saves new photos as JPEG.',

];
