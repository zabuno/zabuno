<?php

declare(strict_types=1);

namespace App\Domain\Content;

use RuntimeException;

/**
 * İçerik sözleşmesinin ihlali — FF-191.
 *
 * Bu bir çalışma zamanı hatası değil, bir YAZIM hatasıdır: kütükteki içerik
 * kodun bir parçasıdır ve testte patlar. Üretimde bu istisnanın atılması,
 * bir sayfanın yanlış kurulmuş olması demektir.
 */
final class PageContentException extends RuntimeException {}
